<?php

namespace App\Communications\OrganizationInbox;

use App\Communications\OrganizationInbox\Exceptions\OrganizationCommunicationResponseException;
use App\Enums\ChurchOrganizationAssignmentStatus;
use App\Enums\OrganizationCommunicationDeclineReasonCode;
use App\Enums\OrganizationCommunicationDeliveryState;
use App\Enums\OrganizationStatus;
use App\Models\ChurchMembership;
use App\Models\Organization;
use App\Models\OrganizationCommunicationDelivery;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * K-ORG-COMMS-001D §39 — the single canonical seam through which a Church
 * may ever record a response against an Organization-distributed
 * delivery. No controller or Filament page mutates
 * `OrganizationCommunicationDelivery`'s response columns directly.
 *
 * "ACCEPT != IMPORT" — accept()/decline() only ever write the four §47
 * response-evidence columns (`accepted_at`, `declined_at`,
 * `decline_reason_code`, `responded_by_church_membership_id`). They never
 * create a ContentItem, Campaign, MediaAsset, Website record, or
 * FaithFlow record — that is reserved for a future, separately
 * commissioned K-ORG-COMMS-001E.
 *
 * Concurrency (§52-§54): both methods take a row lock inside a
 * transaction, then re-read the locked row before deciding anything. A
 * repeat of the *same* response converges idempotently (returns the
 * existing row, no second write). A *different* response after a
 * terminal one is denied. Two racing opposite responses resolve
 * deterministically — whichever transaction commits first wins, the
 * loser sees the now-terminal row under its own lock and is denied.
 */
class OrganizationCommunicationChurchResponseService
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function accept(OrganizationCommunicationDelivery $delivery): OrganizationCommunicationDelivery
    {
        Gate::authorize('respond', $delivery);
        $membership = $this->requireMembership();

        return DB::transaction(function () use ($delivery, $membership): OrganizationCommunicationDelivery {
            $locked = $this->lock($delivery);

            if ($locked->accepted_at !== null) {
                return $locked;
            }

            if ($locked->declined_at !== null) {
                throw OrganizationCommunicationResponseException::conflictingResponse();
            }

            $this->assertRespondable($locked);

            $locked->forceFill([
                'accepted_at' => now(),
                'responded_by_church_membership_id' => $membership->id,
            ])->save();

            return $locked;
        });
    }

    public function decline(OrganizationCommunicationDelivery $delivery, ?OrganizationCommunicationDeclineReasonCode $reasonCode = null): OrganizationCommunicationDelivery
    {
        Gate::authorize('respond', $delivery);
        $membership = $this->requireMembership();

        return DB::transaction(function () use ($delivery, $membership, $reasonCode): OrganizationCommunicationDelivery {
            $locked = $this->lock($delivery);

            if ($locked->declined_at !== null) {
                return $locked;
            }

            if ($locked->accepted_at !== null) {
                throw OrganizationCommunicationResponseException::conflictingResponse();
            }

            $this->assertRespondable($locked);

            $locked->forceFill([
                'declined_at' => now(),
                'decline_reason_code' => $reasonCode,
                'responded_by_church_membership_id' => $membership->id,
            ])->save();

            return $locked;
        });
    }

    private function lock(OrganizationCommunicationDelivery $delivery): OrganizationCommunicationDelivery
    {
        return OrganizationCommunicationDelivery::query()
            ->whereKey($delivery->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * K-ORG-COMMS-001D §12/§33/§36/§37 — gates a genuinely NEW response
     * only. Both accept()/decline() resolve idempotent-repeat and
     * cross-response cases before ever calling this method, so a delivery
     * that already carries any response never reaches these checks.
     */
    private function assertRespondable(OrganizationCommunicationDelivery $delivery): void
    {
        if ($delivery->state === OrganizationCommunicationDeliveryState::WITHDRAWN) {
            throw OrganizationCommunicationResponseException::withdrawn();
        }

        if ($delivery->available_until !== null && $delivery->available_until->isPast()) {
            throw OrganizationCommunicationResponseException::expired();
        }

        if (! $this->churchStillAttached($delivery)) {
            throw OrganizationCommunicationResponseException::detached();
        }

        $organization = Organization::query()->find($delivery->organization_id);

        if ($organization === null || $organization->status !== OrganizationStatus::ACTIVE) {
            throw OrganizationCommunicationResponseException::organizationUnavailable();
        }
    }

    /**
     * K-ORG-COMMS-001D §36 — a Church that has since detached from this
     * Organization (its current assignment no longer points at this
     * Organization, or is no longer Active) may not record a NEW
     * response. This never touches an already-terminal delivery — see the
     * docblock above.
     */
    private function churchStillAttached(OrganizationCommunicationDelivery $delivery): bool
    {
        $assignment = $delivery->church?->currentOrganizationAssignment;

        return $assignment !== null
            && (int) $assignment->organization_id === (int) $delivery->organization_id
            && $assignment->status === ChurchOrganizationAssignmentStatus::ACTIVE;
    }

    private function requireMembership(): ChurchMembership
    {
        $membership = $this->tenantContext->currentMembership();

        abort_if($membership === null, 403);

        return $membership;
    }
}
