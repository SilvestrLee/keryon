<?php

namespace App\Organizations\Communications\Distribution;

use App\Enums\OrganizationAuditEventType;
use App\Enums\OrganizationCapability;
use App\Enums\OrganizationCommunicationDistributionState;
use App\Enums\OrganizationCommunicationRevisionState;
use App\Enums\OrganizationCommunicationTargetMode;
use App\Jobs\MaterializeOrganizationCommunicationDistribution;
use App\Models\OrganizationCommunicationDistribution;
use App\Models\OrganizationCommunicationRevision;
use App\Organizations\Communications\OrganizationCommunicationAudit;
use App\Organizations\Communications\OrganizationCommunicationAuthorizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * K-ORG-COMMS-001C §9/§23/§26 — the HTTP-side of distribution. Authorizes
 * the human action, validates the target against governing scope,
 * creates one durable Pending distribution, commits, then queues the
 * worker. The worker (§61-§62) never receives a live browser
 * authorization context — only this durable, already-authorized
 * distribution row.
 */
class OrganizationCommunicationDistributionManager
{
    public function __construct(
        private readonly OrganizationCommunicationAuthorizer $authorizer,
        private readonly OrganizationCommunicationAudienceResolver $audience,
        private readonly OrganizationCommunicationAudit $audit,
    ) {}

    /** @param list<int>|null $churchIds */
    public function request(
        OrganizationCommunicationRevision $revision,
        OrganizationCommunicationTargetMode $mode,
        ?int $targetUnitId,
        ?array $churchIds,
    ): OrganizationCommunicationDistribution {
        // K-ORG-COMMS-001C §26 — coarse-grained per-revision lock closes
        // the double-click/duplicate-tab race around the
        // check-for-existing-then-create sequence below. Distribution
        // requests are human-paced, infrequent events, so a single lock
        // per revision is not a throughput concern.
        return Cache::lock("org-comms-distribution-request:{$revision->id}", 10)->block(5, function () use ($revision, $mode, $targetUnitId, $churchIds) {
            return DB::transaction(function () use ($revision, $mode, $targetUnitId, $churchIds): OrganizationCommunicationDistribution {
                $lockedRevision = OrganizationCommunicationRevision::query()
                    ->with('communication')
                    ->lockForUpdate()
                    ->findOrFail($revision->id);
                $actor = $this->authorizer->forCommunication(OrganizationCapability::CommunicationsDistribute, $lockedRevision->communication);

                // §11 gates first distribution to Approved only; §49 then
                // explicitly allows a later, distinct distribution (new
                // audience, resend) of a revision that is already
                // Distributed from an earlier one. Draft/In
                // Review/Changes Requested remain denied.
                if (! in_array($lockedRevision->state, [
                    OrganizationCommunicationRevisionState::APPROVED,
                    OrganizationCommunicationRevisionState::DISTRIBUTED,
                ], true)) {
                    throw new LogicException('Only an Approved Organization communication revision may be distributed.');
                }

                $target = $this->audience->validateTarget($lockedRevision->communication, $mode, $targetUnitId, $churchIds);
                $hash = self::hashTarget($mode, $target['unitId'], $target['churchIds']);

                // §26/§49 — reuse a still-live request for the identical
                // revision + target rather than creating a duplicate
                // logical distribution; a distinct target (or a later
                // request once this one has finished) always creates a new
                // deliberate distribution.
                $existing = OrganizationCommunicationDistribution::query()
                    ->where('organization_communication_revision_id', $lockedRevision->id)
                    ->where('target_definition_hash', $hash)
                    ->whereIn('state', [
                        OrganizationCommunicationDistributionState::PENDING->value,
                        OrganizationCommunicationDistributionState::PROCESSING->value,
                    ])
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }

                $distribution = new OrganizationCommunicationDistribution;
                $distribution->forceFill([
                    'organization_id' => $actor->organization_id,
                    'organization_communication_id' => $lockedRevision->organization_communication_id,
                    'organization_communication_revision_id' => $lockedRevision->id,
                    'governing_unit_id' => $lockedRevision->communication->governing_unit_id,
                    'initiated_by_organization_membership_id' => $actor->id,
                    'target_mode' => $mode,
                    'target_unit_id' => $mode === OrganizationCommunicationTargetMode::UNIT_SUBTREE ? $target['unitId'] : null,
                    'target_church_ids' => $target['churchIds'],
                    'target_definition_hash' => $hash,
                ])->save();

                $this->audit->record(
                    OrganizationAuditEventType::COMMUNICATION_DISTRIBUTION_REQUESTED,
                    $distribution,
                    $actor,
                    ['state' => OrganizationCommunicationDistributionState::PENDING->value, 'target_mode' => $mode->value],
                );

                DB::afterCommit(fn () => MaterializeOrganizationCommunicationDistribution::dispatch($distribution->id));

                return $distribution;
            }, 3);
        });
    }

    /** @param list<int>|null $churchIds */
    public static function hashTarget(OrganizationCommunicationTargetMode $mode, int $unitId, ?array $churchIds): string
    {
        $normalized = [
            'mode' => $mode->value,
            'unit_id' => $mode === OrganizationCommunicationTargetMode::UNIT_SUBTREE ? $unitId : null,
            'church_ids' => $churchIds !== null ? collect($churchIds)->sort()->values()->all() : null,
        ];

        return hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));
    }
}
