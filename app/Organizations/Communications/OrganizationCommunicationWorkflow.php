<?php

namespace App\Organizations\Communications;

use App\Enums\OrganizationAuditEventType;
use App\Enums\OrganizationCapability;
use App\Enums\OrganizationCommunicationRevisionState;
use App\Enums\OrganizationCommunicationState;
use App\Models\OrganizationCommunication;
use App\Models\OrganizationCommunicationRevision;
use Illuminate\Support\Facades\DB;
use LogicException;

class OrganizationCommunicationWorkflow
{
    public function __construct(
        private readonly OrganizationCommunicationAuthorizer $authorizer,
        private readonly OrganizationCommunicationAudit $audit,
    ) {}

    public function submit(OrganizationCommunicationRevision $revision): OrganizationCommunicationRevision
    {
        return $this->transitionRevision(
            $revision,
            OrganizationCapability::CommunicationsEdit,
            OrganizationCommunicationRevisionState::DRAFT,
            OrganizationCommunicationRevisionState::IN_REVIEW,
            OrganizationAuditEventType::COMMUNICATION_REVISION_SUBMITTED,
            function (OrganizationCommunicationRevision $locked, int $membershipId): void {
                $locked->submitted_by_organization_membership_id = $membershipId;
                $locked->submitted_at = now();
                $locked->reviewed_by_organization_membership_id = null;
                $locked->review_feedback = null;
                $locked->changes_requested_at = null;
            },
        );
    }

    public function requestChanges(
        OrganizationCommunicationRevision $revision,
        string $feedback,
    ): OrganizationCommunicationRevision {
        $feedback = trim($feedback);
        if ($feedback === '') {
            throw new LogicException('Review feedback is required when requesting changes.');
        }

        return $this->transitionRevision(
            $revision,
            OrganizationCapability::CommunicationsApprove,
            OrganizationCommunicationRevisionState::IN_REVIEW,
            OrganizationCommunicationRevisionState::CHANGES_REQUESTED,
            OrganizationAuditEventType::COMMUNICATION_REVISION_CHANGES_REQUESTED,
            function (OrganizationCommunicationRevision $locked, int $membershipId) use ($feedback): void {
                $locked->reviewed_by_organization_membership_id = $membershipId;
                $locked->review_feedback = $feedback;
                $locked->changes_requested_at = now();
            },
        );
    }

    public function returnToDraft(OrganizationCommunicationRevision $revision): OrganizationCommunicationRevision
    {
        return DB::transaction(function () use ($revision): OrganizationCommunicationRevision {
            $locked = $this->lockedRevision($revision);
            $this->authorizer->forCommunication(OrganizationCapability::CommunicationsEdit, $locked->communication);
            $this->assertState($locked, OrganizationCommunicationRevisionState::CHANGES_REQUESTED);
            $locked->forceFill(['state' => OrganizationCommunicationRevisionState::DRAFT])->save();

            return $locked->fresh();
        }, 3);
    }

    public function approve(OrganizationCommunicationRevision $revision): OrganizationCommunicationRevision
    {
        return $this->transitionRevision(
            $revision,
            OrganizationCapability::CommunicationsApprove,
            OrganizationCommunicationRevisionState::IN_REVIEW,
            OrganizationCommunicationRevisionState::APPROVED,
            OrganizationAuditEventType::COMMUNICATION_REVISION_APPROVED,
            function (OrganizationCommunicationRevision $locked, int $membershipId): void {
                $locked->reviewed_by_organization_membership_id = $membershipId;
                $locked->approved_by_organization_membership_id = $membershipId;
                $locked->approved_at = now();
                $locked->review_feedback = null;
                $locked->changes_requested_at = null;
            },
        );
    }

    public function close(OrganizationCommunication $communication): OrganizationCommunication
    {
        return $this->transitionRoot(
            $communication,
            [OrganizationCommunicationState::DRAFT, OrganizationCommunicationState::ACTIVE],
            OrganizationCommunicationState::CLOSED,
            OrganizationAuditEventType::COMMUNICATION_CLOSED,
        );
    }

    public function withdraw(OrganizationCommunication $communication): OrganizationCommunication
    {
        return $this->transitionRoot(
            $communication,
            [OrganizationCommunicationState::ACTIVE],
            OrganizationCommunicationState::WITHDRAWN,
            OrganizationAuditEventType::COMMUNICATION_WITHDRAWN,
        );
    }

    /**
     * @param  callable(OrganizationCommunicationRevision, int): void  $mutate
     */
    private function transitionRevision(
        OrganizationCommunicationRevision $revision,
        OrganizationCapability $capability,
        OrganizationCommunicationRevisionState $from,
        OrganizationCommunicationRevisionState $to,
        OrganizationAuditEventType $event,
        callable $mutate,
    ): OrganizationCommunicationRevision {
        return DB::transaction(function () use ($revision, $capability, $from, $to, $event, $mutate): OrganizationCommunicationRevision {
            $locked = $this->lockedRevision($revision);
            $actor = $this->authorizer->forCommunication($capability, $locked->communication);
            $this->assertState($locked, $from);
            $mutate($locked, $actor->id);
            $locked->state = $to;
            $locked->save();
            $this->audit->record(
                $event,
                $locked,
                $actor,
                ['state' => $to->value, 'version' => $locked->version],
                ['state' => $from->value, 'version' => $locked->version],
            );

            return $locked->fresh();
        }, 3);
    }

    /** @param list<OrganizationCommunicationState> $allowed */
    private function transitionRoot(
        OrganizationCommunication $communication,
        array $allowed,
        OrganizationCommunicationState $to,
        OrganizationAuditEventType $event,
    ): OrganizationCommunication {
        return DB::transaction(function () use ($communication, $allowed, $to, $event): OrganizationCommunication {
            $locked = OrganizationCommunication::query()->lockForUpdate()->findOrFail($communication->id);
            $actor = $this->authorizer->forCommunication(OrganizationCapability::CommunicationsWithdraw, $locked);
            if (! in_array($locked->state, $allowed, true)) {
                throw new LogicException("Organization communication cannot transition from [{$locked->state->value}] to [{$to->value}].");
            }
            $previous = $locked->state;
            $locked->forceFill(['state' => $to])->save();
            $this->audit->record($event, $locked, $actor, ['state' => $to->value], ['state' => $previous->value]);

            return $locked->fresh();
        }, 3);
    }

    private function lockedRevision(OrganizationCommunicationRevision $revision): OrganizationCommunicationRevision
    {
        return OrganizationCommunicationRevision::query()
            ->with('communication')
            ->lockForUpdate()
            ->findOrFail($revision->id);
    }

    private function assertState(
        OrganizationCommunicationRevision $revision,
        OrganizationCommunicationRevisionState $expected,
    ): void {
        if ($revision->state !== $expected) {
            throw new LogicException("Revision cannot transition from [{$revision->state->value}]; [{$expected->value}] is required.");
        }
    }
}
