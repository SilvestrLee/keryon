<?php

namespace App\Domain;

use App\Enums\ChurchDomainEventType;
use App\Enums\DomainFailureCode;
use App\Enums\DomainStatus;
use App\Enums\DomainTlsStatus;
use App\Models\Church;
use App\Models\ChurchDomain;
use App\Models\ChurchDomainEvent;
use App\Models\ChurchMembership;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ChurchDomainLifecycle
{
    public function ownershipVerified(ChurchDomain $domain, ?ChurchMembership $actor = null, ?string $correlationId = null): ChurchDomain
    {
        return $this->transition($domain, function (ChurchDomain $locked) use ($actor, $correlationId): void {
            $locked->forceFill([
                'ownership_verified_at' => $locked->ownership_verified_at ?? now(),
                'last_checked_at' => now(),
                'failure_code' => null,
                'consecutive_failures' => 0,
            ])->save();
            $this->record($locked, ChurchDomainEventType::OwnershipVerified, $actor, null, $correlationId);
            $this->markVerifiedWhenComplete($locked);
        });
    }

    public function routingVerified(ChurchDomain $domain, ?ChurchMembership $actor = null, ?string $correlationId = null): ChurchDomain
    {
        return $this->transition($domain, function (ChurchDomain $locked) use ($actor, $correlationId): void {
            $locked->forceFill([
                'routing_verified_at' => $locked->routing_verified_at ?? now(),
                'last_checked_at' => now(),
                'failure_code' => null,
                'consecutive_failures' => 0,
            ])->save();
            $this->record($locked, ChurchDomainEventType::RoutingVerified, $actor, null, $correlationId);
            $this->markVerifiedWhenComplete($locked);
        });
    }

    public function verificationFailed(ChurchDomain $domain, DomainFailureCode $code, ?string $correlationId = null): ChurchDomain
    {
        return $this->transition($domain, function (ChurchDomain $locked) use ($code, $correlationId): void {
            $locked->forceFill([
                'last_checked_at' => now(),
                'failure_code' => $code->value,
                'consecutive_failures' => min(65535, $locked->consecutive_failures + 1),
            ])->save();
            $this->record($locked, ChurchDomainEventType::VerificationFailed, null, $code, $correlationId);
        });
    }

    public function tlsProvisioning(ChurchDomain $domain, ?string $correlationId = null): ChurchDomain
    {
        return $this->transition($domain, function (ChurchDomain $locked) use ($correlationId): void {
            if ($locked->ownership_verified_at === null || $locked->routing_verified_at === null) {
                throw ValidationException::withMessages(['domain' => 'Ownership and routing must be verified before TLS provisioning.']);
            }
            $locked->forceFill(['tls_status' => DomainTlsStatus::Provisioning, 'failure_code' => null])->save();
            $this->record($locked, ChurchDomainEventType::TlsProvisioning, null, null, $correlationId);
        });
    }

    /**
     * $recordCheck: set only by PollChurchDomainTls (K-DOMAIN-001E-R1 §3) —
     * a real provider TLS-status check occurred, so last_checked_at moves.
     * The initial-verification call site (VerifyChurchDomain) leaves this
     * false, preserving its existing behaviour exactly.
     */
    public function tlsReady(ChurchDomain $domain, ?string $correlationId = null, bool $recordCheck = false): ChurchDomain
    {
        return $this->transition($domain, function (ChurchDomain $locked) use ($correlationId, $recordCheck): void {
            if ($locked->ownership_verified_at === null || $locked->routing_verified_at === null) {
                throw ValidationException::withMessages(['domain' => 'Ownership and routing must be verified before TLS can become ready.']);
            }
            $locked->forceFill([
                'tls_status' => DomainTlsStatus::Ready,
                'tls_ready_at' => now(),
                'failure_code' => null,
                'consecutive_failures' => 0,
                ...($recordCheck ? ['last_checked_at' => now()] : []),
            ])->save();
            $this->record($locked, ChurchDomainEventType::TlsReady, null, null, $correlationId);
        });
    }

    /** @see tlsReady() for $recordCheck */
    public function tlsFailed(ChurchDomain $domain, DomainFailureCode $code = DomainFailureCode::CertificateFailed, ?string $correlationId = null, bool $recordCheck = false): ChurchDomain
    {
        return $this->transition($domain, function (ChurchDomain $locked) use ($code, $correlationId, $recordCheck): void {
            $locked->forceFill([
                'tls_status' => DomainTlsStatus::Failed,
                'failure_code' => $code->value,
                ...($recordCheck ? ['last_checked_at' => now()] : []),
            ])->save();
            $this->record($locked, ChurchDomainEventType::TlsFailed, null, $code, $correlationId);
        });
    }

    /**
     * TLS polling performed a real provider check that changed nothing else
     * — provider status is still Pending, or the provider API itself was
     * Unavailable. Only last_checked_at moves; the confirmed-failure streak
     * is untouched in either direction. See K-DOMAIN-001E-R1 §3.
     */
    public function recordTlsCheck(ChurchDomain $domain, ?string $correlationId = null): ChurchDomain
    {
        return $this->transition($domain, function (ChurchDomain $locked): void {
            $locked->forceFill(['last_checked_at' => now()])->save();
        });
    }

    public function activate(ChurchDomain $domain, ?ChurchMembership $actor = null, ?string $correlationId = null): ChurchDomain
    {
        return $this->transition($domain, function (ChurchDomain $locked) use ($actor, $correlationId): void {
            $church = Church::query()->find($locked->church_id);
            if ($church === null || ! $church->is_active
                || $locked->ownership_verified_at === null
                || $locked->routing_verified_at === null
                || $locked->tls_status !== DomainTlsStatus::Ready
                || $locked->tls_ready_at === null
                || $locked->disabled_at !== null
                || $locked->released_at !== null) {
                throw ValidationException::withMessages(['domain' => 'This domain is not eligible for activation.']);
            }
            $locked->forceFill(['status' => DomainStatus::Active, 'activated_at' => now(), 'failure_code' => null])->save();
            $this->record($locked, ChurchDomainEventType::Activated, $actor, null, $correlationId);
        });
    }

    public function degrade(ChurchDomain $domain, DomainFailureCode $code, ?string $correlationId = null): ChurchDomain
    {
        return $this->transition($domain, function (ChurchDomain $locked) use ($code, $correlationId): void {
            if (! $locked->isDueForAutomaticDegradation()) {
                throw ValidationException::withMessages(['domain' => 'A domain requires three confirmed failures spanning at least 24 hours before degradation.']);
            }
            $locked->forceFill(['status' => DomainStatus::Degraded, 'is_primary' => false, 'failure_code' => $code->value])->save();
            $this->record($locked, ChurchDomainEventType::Degraded, null, $code, $correlationId);
        });
    }

    /**
     * One whole-cycle outcome, exactly one persistence mutation — see
     * K-DOMAIN-001E §7. Never call this alongside per-sub-check mutations
     * for the same cycle.
     */
    public function healthCycleSucceeded(ChurchDomain $domain, ?string $correlationId = null): ChurchDomain
    {
        return $this->transition($domain, function (ChurchDomain $locked) use ($correlationId): void {
            $wasDegraded = $locked->status === DomainStatus::Degraded;

            $locked->forceFill([
                'status' => $wasDegraded ? DomainStatus::Active : $locked->status,
                'last_checked_at' => now(),
                'consecutive_failures' => 0,
                'failure_streak_started_at' => null,
                'failure_code' => null,
            ])->save();

            // is_primary is deliberately untouched — recovery never
            // restores it automatically (§13); a different domain may have
            // become primary while this one was degraded.
            if ($wasDegraded) {
                $this->record($locked, ChurchDomainEventType::Recovered, null, null, $correlationId);
            }
        });
    }

    public function healthCycleFailed(ChurchDomain $domain, DomainFailureCode $code, ?string $correlationId = null): ChurchDomain
    {
        return $this->transition($domain, function (ChurchDomain $locked) use ($code, $correlationId): void {
            $startingNewStreak = $locked->failure_streak_started_at === null;

            $locked->forceFill([
                'last_checked_at' => now(),
                'failure_streak_started_at' => $startingNewStreak ? now() : $locked->failure_streak_started_at,
                'consecutive_failures' => $startingNewStreak ? 1 : min(65535, $locked->consecutive_failures + 1),
                'failure_code' => $code->value,
            ])->save();
            $this->record($locked, ChurchDomainEventType::HealthCheckFailed, null, $code, $correlationId);
        });
    }

    /**
     * An indeterminate cycle proves neither health nor failure — only the
     * fact that a check was attempted is recorded. Do not touch the
     * failure streak in either direction (§10).
     */
    public function healthCycleIndeterminate(ChurchDomain $domain, ?string $correlationId = null): ChurchDomain
    {
        return $this->transition($domain, function (ChurchDomain $locked): void {
            $locked->forceFill(['last_checked_at' => now()])->save();
        });
    }

    public function makePrimary(ChurchDomain $domain, ChurchMembership $actor): ChurchDomain
    {
        return DB::transaction(function () use ($domain, $actor): ChurchDomain {
            Church::query()->lockForUpdate()->findOrFail($domain->church_id);
            $locked = ChurchDomain::withoutGlobalScope('church_tenant')->lockForUpdate()->findOrFail($domain->getKey());
            if (! $locked->isEligible()) {
                throw ValidationException::withMessages(['domain' => 'Only an active, fully verified, TLS-ready domain can become primary.']);
            }
            ChurchDomain::withoutGlobalScope('church_tenant')
                ->where('church_id', $locked->church_id)
                ->where('id', '!=', $locked->id)
                ->where('is_primary', true)
                ->update(['is_primary' => false, 'updated_at' => now()]);
            $locked->forceFill(['is_primary' => true])->save();
            $this->record($locked, ChurchDomainEventType::PrimaryChanged, $actor);

            return $locked->fresh();
        }, 3);
    }

    public function disable(ChurchDomain $domain, ChurchMembership $actor): ChurchDomain
    {
        return $this->transition($domain, function (ChurchDomain $locked) use ($actor): void {
            $locked->forceFill([
                'status' => DomainStatus::Disabled,
                'is_primary' => false,
                'disabled_at' => now(),
                'failure_code' => DomainFailureCode::Disabled->value,
            ])->save();
            $this->record($locked, ChurchDomainEventType::Disabled, $actor, DomainFailureCode::Disabled);
        });
    }

    public function release(ChurchDomain $domain, ChurchMembership $actor): ChurchDomain
    {
        return DB::transaction(function () use ($domain, $actor): ChurchDomain {
            $locked = ChurchDomain::withoutGlobalScope('church_tenant')->lockForUpdate()->findOrFail($domain->getKey());
            if ($locked->status === DomainStatus::Released) {
                return $locked;
            }
            $locked->forceFill([
                'status' => DomainStatus::Released,
                'is_primary' => false,
                'disabled_at' => $locked->disabled_at ?? now(),
                'released_at' => now(),
                'failure_code' => DomainFailureCode::Disabled->value,
            ])->save();
            $this->record($locked, ChurchDomainEventType::Released, $actor, DomainFailureCode::Disabled);

            return $locked->fresh();
        }, 3);
    }

    public function record(ChurchDomain $domain, ChurchDomainEventType $type, ?ChurchMembership $actor = null, ?DomainFailureCode $failure = null, ?string $correlationId = null): ChurchDomainEvent
    {
        return ChurchDomainEvent::query()->firstOrCreate([
            'church_domain_id' => $domain->getKey(),
            'event_type' => $type->value,
            'correlation_id' => $correlationId ?? (string) Str::uuid(),
        ], [
            'church_id' => $domain->church_id,
            'actor_user_id' => $actor?->user_id,
            'actor_church_membership_id' => $actor?->getKey(),
            'failure_code' => $failure?->value,
            'occurred_at' => now(),
        ]);
    }

    private function markVerifiedWhenComplete(ChurchDomain $domain): void
    {
        if (in_array($domain->status, [DomainStatus::PendingVerification, DomainStatus::Verified], true)
            && $domain->ownership_verified_at !== null && $domain->routing_verified_at !== null) {
            $domain->forceFill(['status' => DomainStatus::Verified])->save();
        }
    }

    private function transition(ChurchDomain $domain, callable $transition): ChurchDomain
    {
        return DB::transaction(function () use ($domain, $transition): ChurchDomain {
            $locked = ChurchDomain::withoutGlobalScope('church_tenant')->lockForUpdate()->findOrFail($domain->getKey());
            if (in_array($locked->status, [DomainStatus::Disabled, DomainStatus::Released], true)) {
                throw ValidationException::withMessages(['domain' => 'This domain is disabled or released.']);
            }
            $transition($locked);

            return $locked->fresh();
        }, 3);
    }
}
