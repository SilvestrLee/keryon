<?php

namespace App\Campaigns;

use App\Enums\CampaignStatus;
use App\Enums\Capability;
use App\Models\CampaignCommunication;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use LogicException;

class CampaignCommunicationContext
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function forContentCreation(int $communicationId): CampaignCommunication
    {
        return $this->resolveActionable($communicationId, Capability::ContentManage, requireUnlinked: true);
    }

    public function forFaithFlow(int $communicationId): CampaignCommunication
    {
        $communication = $this->resolveActionable($communicationId, Capability::FaithflowUse, requireUnlinked: true);
        $membership = $this->tenant->currentMembership();

        if (! $membership?->hasCapability(Capability::ContentManage)) {
            throw new AuthorizationException('Content Studio access is required for Campaign-context FaithFlow creation.');
        }

        return $communication;
    }

    public function forFaithFlowHandoff(int $communicationId): CampaignCommunication
    {
        $communication = $this->resolveInFlightFaithFlow($communicationId);
        $membership = $this->tenant->currentMembership();

        if (! $membership?->hasCapability(Capability::ContentManage)) {
            throw new AuthorizationException('Content Studio access is required for Campaign-context FaithFlow handoff.');
        }

        return $communication;
    }

    private function resolveInFlightFaithFlow(int $communicationId): CampaignCommunication
    {
        $communication = $this->resolveOwned($communicationId, Capability::FaithflowUse);

        if ($communication->cancelled_at !== null) {
            throw new LogicException('Cancelled Campaign communication cannot complete FaithFlow handoff.');
        }

        if ($communication->campaign->status === CampaignStatus::ARCHIVED) {
            throw new LogicException('Archived Campaign communication cannot complete FaithFlow handoff.');
        }

        return $communication;
    }

    public function forFaithFlowView(int $communicationId): CampaignCommunication
    {
        return $this->resolveOwned($communicationId, Capability::FaithflowUse);
    }

    private function resolveActionable(int $communicationId, Capability $adjacentCapability, bool $requireUnlinked): CampaignCommunication
    {
        $communication = $this->resolveOwned($communicationId, $adjacentCapability);

        if ($communication->cancelled_at !== null) {
            throw new LogicException('Restore this communication before creating content for it.');
        }

        if (! in_array($communication->campaign->status, [
            CampaignStatus::DRAFT,
            CampaignStatus::PLANNED,
            CampaignStatus::ACTIVE,
        ], true)) {
            throw new LogicException('New contextual content cannot be created for a completed or archived Campaign.');
        }

        if ($requireUnlinked && $communication->content_item_id !== null) {
            throw new LogicException('This communication already has linked Content Studio content.');
        }

        return $communication;
    }

    private function resolveOwned(int $communicationId, Capability $adjacentCapability): CampaignCommunication
    {
        $membership = $this->tenant->currentMembership();

        if (
            $membership === null
            || ! $membership->hasCapability(Capability::CampaignsManage)
            || ! $membership->hasCapability($adjacentCapability)
        ) {
            throw new AuthorizationException('The active Church membership cannot use this Campaign content context.');
        }

        $communication = CampaignCommunication::query()
            ->with('campaign')
            ->findOrFail($communicationId);

        if (
            $communication->church_id !== $membership->church_id
            || $communication->campaign?->church_id !== $membership->church_id
        ) {
            throw new AuthorizationException('The Campaign communication does not belong to the active Church.');
        }

        return $communication;
    }
}
