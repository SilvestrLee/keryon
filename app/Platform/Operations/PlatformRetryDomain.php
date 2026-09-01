<?php

namespace App\Platform\Operations;

use App\Enums\DomainStatus;
use App\Enums\PlatformAuditEventType;
use App\Enums\PlatformAuditReasonCategory;
use App\Enums\PlatformAuditTargetType;
use App\Enums\PlatformCapability;
use App\Jobs\VerifyChurchDomain;
use App\Models\ChurchDomain;
use App\Platform\PlatformAudit;
use DomainException;
use Illuminate\Support\Facades\DB;

final class PlatformRetryDomain extends PlatformOperation
{
    public function execute(int $domainId, PlatformAuditReasonCategory $reason, string $note, string $correlationId): PlatformOperationResult
    {
        $actor = $this->actor(PlatformCapability::PlatformDomainRetry);
        $this->requireReason($note);
        $this->requireCorrelation($correlationId);
        if (config('public-website.custom_domains.dns_resolver') === 'unavailable') {
            throw new DomainException('Domain verification is not currently available in this environment.');
        }

        return DB::transaction(function () use ($domainId, $reason, $note, $correlationId, $actor): PlatformOperationResult {
            $domain = ChurchDomain::withoutGlobalScope('church_tenant')->lockForUpdate()->findOrFail($domainId);
            if (in_array($domain->status, [DomainStatus::Disabled, DomainStatus::Released], true)) {
                throw new DomainException('This domain is not eligible for verification retry.');
            }if ($this->prior(PlatformAuditEventType::DOMAIN_VERIFICATION_RETRY_REQUESTED->value, PlatformAuditTargetType::CHURCH_DOMAIN->value, $domainId, $correlationId)) {
                return new PlatformOperationResult('This domain retry was already requested.', 'church_domain', $domainId, ['status' => $domain->status->value], false);
            }VerifyChurchDomain::dispatch($domain->id, $correlationId)->afterCommit();
            PlatformAudit::record(PlatformAuditEventType::DOMAIN_VERIFICATION_RETRY_REQUESTED, PlatformAuditTargetType::CHURCH_DOMAIN, $domainId, $actor, ['status' => $domain->status->value, 'tls_status' => $domain->tls_status->value, 'retry_requested' => false], ['status' => $domain->status->value, 'tls_status' => $domain->tls_status->value, 'retry_requested' => true, 'actor_role' => $actor->role->value, 'capability' => PlatformCapability::PlatformDomainRetry->value, 'result' => 'queued'], $reason, $note, $correlationId);

            return new PlatformOperationResult('Domain verification retry queued.', 'church_domain', $domainId, ['status' => $domain->status->value], true);
        }, 3);
    }
}
