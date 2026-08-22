<?php

namespace App\Campaigns;

use App\Enums\Capability;
use App\Models\Campaign;
use App\Models\CampaignCommunication;
use App\Models\ChurchMembership;
use App\Models\ContentItem;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use LogicException;

class CampaignCommunicationManager
{
    public function __construct(private readonly TenantContext $tenant) {}

    /** @param array{title: string, purpose?: ?string, channel: mixed, target_at?: mixed, sort_order?: int} $attributes */
    public function add(Campaign $campaign, array $attributes): CampaignCommunication
    {
        $membership = $this->authorizedForCampaign($campaign);

        $communication = new CampaignCommunication($attributes);
        $communication->forceFill([
            'church_id' => $membership->church_id,
            'campaign_id' => $campaign->id,
        ]);
        $communication->save();

        return $communication;
    }

    /** @param array{title?: string, purpose?: ?string, channel?: mixed, target_at?: mixed, sort_order?: int} $attributes */
    public function update(CampaignCommunication $communication, array $attributes): CampaignCommunication
    {
        $this->authorizedForCommunication($communication);
        $communication->fill($attributes)->save();

        return $communication;
    }

    public function linkContentItem(CampaignCommunication $communication, ContentItem $contentItem): CampaignCommunication
    {
        $membership = $this->authorizedForCommunication($communication);

        if ($contentItem->church_id !== $membership->church_id) {
            throw new AuthorizationException('The Content Studio item does not belong to the active Church.');
        }

        $duplicate = CampaignCommunication::query()
            ->where('campaign_id', $communication->campaign_id)
            ->where('content_item_id', $contentItem->id)
            ->whereKeyNot($communication->id)
            ->exists();

        if ($duplicate) {
            throw new LogicException('This Content Studio item is already linked to the Campaign.');
        }

        $communication->forceFill(['content_item_id' => $contentItem->id])->save();

        return $communication;
    }

    public function unlinkContentItem(CampaignCommunication $communication): CampaignCommunication
    {
        $this->authorizedForCommunication($communication);
        $communication->forceFill(['content_item_id' => null])->save();

        return $communication;
    }

    public function cancel(CampaignCommunication $communication): CampaignCommunication
    {
        $this->authorizedForCommunication($communication);
        $communication->forceFill(['cancelled_at' => now()])->save();

        return $communication;
    }

    public function restore(CampaignCommunication $communication): CampaignCommunication
    {
        $this->authorizedForCommunication($communication);
        $communication->forceFill(['cancelled_at' => null])->save();

        return $communication;
    }

    public function delete(CampaignCommunication $communication): void
    {
        $this->authorizedForCommunication($communication);
        $communication->delete();
    }

    private function authorizedForCampaign(Campaign $campaign): ChurchMembership
    {
        $membership = $this->authorizedMembership();

        if ($campaign->church_id !== $membership->church_id) {
            throw new AuthorizationException('The Campaign does not belong to the active Church.');
        }

        return $membership;
    }

    private function authorizedForCommunication(CampaignCommunication $communication): ChurchMembership
    {
        $membership = $this->authorizedMembership();

        if ($communication->church_id !== $membership->church_id || $communication->campaign?->church_id !== $membership->church_id) {
            throw new AuthorizationException('The Campaign communication does not belong to the active Church.');
        }

        return $membership;
    }

    private function authorizedMembership(): ChurchMembership
    {
        $membership = $this->tenant->currentMembership();

        if ($membership === null || ! $membership->hasCapability(Capability::CampaignsManage)) {
            throw new AuthorizationException('You are not authorized to manage Campaign communications.');
        }

        return $membership;
    }
}
