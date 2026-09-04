<?php

namespace App\Jobs;

use App\Enums\OrganizationAuditEventType;
use App\Enums\OrganizationCommunicationDistributionState;
use App\Enums\OrganizationCommunicationRevisionState;
use App\Enums\OrganizationCommunicationState;
use App\Enums\OrganizationCommunicationTargetMode;
use App\Models\OrganizationCommunicationDistribution;
use App\Organizations\Communications\Distribution\OrganizationCommunicationAudienceResolver;
use App\Organizations\Communications\OrganizationCommunicationAudit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * K-ORG-COMMS-001C §23/§61-§62 — the durable-authority worker. Receives
 * only a distribution id — never a live browser OrganizationContext
 * (§61). Everything it needs (Organization, revision, governing scope,
 * deliberate target) already lives on the durable, already-authorized
 * `OrganizationCommunicationDistribution` row created by
 * `OrganizationCommunicationDistributionManager::request()`.
 *
 * `ShouldBeUnique` (Laravel's native mechanism, backed by the existing
 * database cache-lock table — see `TenantAwareJob`'s own precedent)
 * prevents two workers from concurrently materializing the same
 * distribution (§27/§28). The `(distribution_id, church_id)` unique
 * constraint plus `upsert()` writes are the second, DB-level backstop
 * (§20/§27) that make retries converge without duplicate deliveries.
 */
class MaterializeOrganizationCommunicationDistribution implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    public function __construct(public readonly int $distributionId) {}

    public function uniqueId(): string
    {
        return (string) $this->distributionId;
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(OrganizationCommunicationAudienceResolver $audience, OrganizationCommunicationAudit $audit): void
    {
        $distribution = OrganizationCommunicationDistribution::query()
            ->with(['communication', 'revision'])
            ->find($this->distributionId);

        if ($distribution === null || $distribution->state === OrganizationCommunicationDistributionState::COMPLETED) {
            return;
        }

        if ($distribution->state === OrganizationCommunicationDistributionState::FAILED) {
            // A prior attempt already reached a terminal Failed state
            // (e.g. zero eligible recipients) — not retried automatically.
            return;
        }

        // §16 locked decision: snapshot_at is set once, at the start of
        // the first genuine materialization attempt, and preserved across
        // any retry of this same logical run.
        DB::transaction(function () use ($distribution): void {
            $locked = OrganizationCommunicationDistribution::query()->lockForUpdate()->find($distribution->id);
            if ($locked->snapshot_at === null) {
                $locked->forceFill([
                    'state' => OrganizationCommunicationDistributionState::PROCESSING,
                    'started_at' => $locked->started_at ?? now(),
                    'snapshot_at' => now(),
                ])->save();
            } elseif ($locked->state === OrganizationCommunicationDistributionState::PENDING) {
                $locked->forceFill(['state' => OrganizationCommunicationDistributionState::PROCESSING])->save();
            }
        });

        $distribution->refresh();
        $revision = $distribution->revision;

        // Structural re-validation (§62) — the worker trusts the durable
        // record, not a live session, but still confirms the revision
        // hasn't become structurally invalid since request time. Already
        // Distributed is fine (a later distribution of the same revision).
        if (! in_array($revision->state, [
            OrganizationCommunicationRevisionState::APPROVED,
            OrganizationCommunicationRevisionState::DISTRIBUTED,
        ], true)) {
            $this->markFailed($distribution, $audit, 'The revision is no longer in an approved state.');

            return;
        }

        $boundaryUnitId = $distribution->target_mode === OrganizationCommunicationTargetMode::UNIT_SUBTREE
            ? $distribution->target_unit_id
            : $distribution->governing_unit_id;

        $chunkSize = max(1, (int) config('organization-communications.distribution_chunk_size'));
        $recipients = $audience->resolveForSnapshot($distribution->organization_id, $boundaryUnitId, $distribution->target_church_ids);

        foreach ($recipients->chunk($chunkSize) as $chunk) {
            $rows = $chunk->map(fn (object $row): array => [
                'uuid' => (string) Str::uuid(),
                'organization_id' => $distribution->organization_id,
                'organization_communication_distribution_id' => $distribution->id,
                'organization_communication_revision_id' => $distribution->organization_communication_revision_id,
                'church_id' => $row->church_id,
                'church_organization_assignment_id' => $row->assignment_id,
                'state' => 'available',
                'available_at' => $distribution->snapshot_at,
                'available_until' => $revision->available_until,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

            if ($rows !== []) {
                // §17/§27 — idempotent by construction: a retried chunk
                // upserts the identical row rather than violating the
                // unique constraint or duplicating the recipient.
                DB::table('organization_communication_deliveries')->upsert(
                    $rows,
                    ['organization_communication_distribution_id', 'church_id'],
                    ['available_at', 'available_until', 'updated_at'],
                );
            }
        }

        $deliveredCount = $distribution->deliveries()->count();

        // §35 — zero eligible recipients is a factual failure, not a
        // misleading success.
        if ($deliveredCount === 0) {
            $this->markFailed($distribution, $audit, 'No eligible Churches were found for the selected audience.');

            return;
        }

        DB::transaction(function () use ($distribution, $deliveredCount, $audit): void {
            $locked = OrganizationCommunicationDistribution::query()->lockForUpdate()->find($distribution->id);
            $locked->forceFill([
                'resolved_recipient_count' => $deliveredCount,
                'delivered_count' => $deliveredCount,
                'state' => OrganizationCommunicationDistributionState::COMPLETED,
                'completed_at' => now(),
            ])->save();

            // §13/§77 — Distributed only after materialization actually
            // completes; a later distribution of an already-Distributed
            // revision is a no-op transition (idempotent).
            $revision = $locked->revision()->lockForUpdate()->first();
            if ($revision->state !== OrganizationCommunicationRevisionState::DISTRIBUTED) {
                $revision->forceFill(['state' => OrganizationCommunicationRevisionState::DISTRIBUTED])->save();
            }

            // §12/§78 — Active only on the first completed distribution;
            // revision authoring/versioning remains unaffected.
            $communication = $locked->communication()->lockForUpdate()->first();
            if ($communication->state === OrganizationCommunicationState::DRAFT) {
                $communication->forceFill(['state' => OrganizationCommunicationState::ACTIVE])->save();
            }

            $audit->record(
                OrganizationAuditEventType::COMMUNICATION_DISTRIBUTION_COMPLETED,
                $locked,
                $locked->initiatorMembership,
                ['state' => OrganizationCommunicationDistributionState::COMPLETED->value, 'delivered_count' => $deliveredCount],
            );
        });
    }

    public function failed(Throwable $exception): void
    {
        $distribution = OrganizationCommunicationDistribution::query()->find($this->distributionId);
        if ($distribution === null || $distribution->state === OrganizationCommunicationDistributionState::COMPLETED) {
            return;
        }

        Log::error('Organization communication distribution materialization failed.', [
            'distribution_id' => $this->distributionId,
            'exception' => $exception::class,
        ]);

        $this->markFailed($distribution, app(OrganizationCommunicationAudit::class), 'Distribution processing failed after all retry attempts.');
    }

    private function markFailed(OrganizationCommunicationDistribution $distribution, OrganizationCommunicationAudit $audit, string $reason): void
    {
        DB::transaction(function () use ($distribution, $reason, $audit): void {
            $locked = OrganizationCommunicationDistribution::query()->lockForUpdate()->find($distribution->id);
            if ($locked === null || $locked->state === OrganizationCommunicationDistributionState::COMPLETED || $locked->state === OrganizationCommunicationDistributionState::FAILED) {
                return;
            }

            $locked->forceFill([
                'state' => OrganizationCommunicationDistributionState::FAILED,
                'failed_at' => now(),
                'failure_reason' => $reason,
            ])->save();

            $audit->record(
                OrganizationAuditEventType::COMMUNICATION_DISTRIBUTION_FAILED,
                $locked,
                $locked->initiatorMembership,
                ['state' => OrganizationCommunicationDistributionState::FAILED->value],
            );
        });
    }
}
