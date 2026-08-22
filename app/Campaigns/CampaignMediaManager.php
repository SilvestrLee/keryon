<?php

namespace App\Campaigns;

use App\Enums\Capability;
use App\Models\Campaign;
use App\Models\CampaignMedia;
use App\Models\ChurchMembership;
use App\Models\MediaAsset;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use LogicException;

class CampaignMediaManager
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function attach(Campaign $campaign, MediaAsset $asset, ?string $label = null): CampaignMedia
    {
        $membership = $this->authorizedMembership();
        $this->assertSameChurch($campaign, $asset, $membership);

        if ($asset->trashed()) {
            throw new LogicException('A deleted institutional asset cannot be attached to a Campaign.');
        }

        $existing = CampaignMedia::query()
            ->where('campaign_id', $campaign->id)
            ->where('media_asset_id', $asset->id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $association = new CampaignMedia([
            'label' => filled($label) ? trim($label) : null,
            'sort_order' => ($campaign->mediaAssociations()->max('sort_order') ?? -1) + 1,
        ]);
        $association->forceFill([
            'church_id' => $membership->church_id,
            'campaign_id' => $campaign->id,
            'media_asset_id' => $asset->id,
        ])->save();

        return $association;
    }

    public function update(CampaignMedia $association, ?string $label): CampaignMedia
    {
        $membership = $this->authorizedMembership();

        if ($association->church_id !== $membership->church_id) {
            throw new AuthorizationException('The Campaign media association does not belong to the active Church.');
        }

        $association->update(['label' => filled($label) ? trim($label) : null]);

        return $association;
    }

    public function detach(CampaignMedia $association): void
    {
        $membership = $this->authorizedMembership();

        if ($association->church_id !== $membership->church_id) {
            throw new AuthorizationException('The Campaign media association does not belong to the active Church.');
        }

        $association->delete();
    }

    private function authorizedMembership(): ChurchMembership
    {
        $membership = $this->tenant->currentMembership();

        if (
            $membership === null
            || ! $membership->hasCapability(Capability::CampaignsManage)
            || ! $membership->hasCapability(Capability::MediaView)
        ) {
            throw new AuthorizationException('Campaign and Media access are both required to manage Campaign assets.');
        }

        return $membership;
    }

    private function assertSameChurch(Campaign $campaign, MediaAsset $asset, ChurchMembership $membership): void
    {
        if (
            $campaign->church_id !== $membership->church_id
            || $asset->church_id !== $membership->church_id
        ) {
            throw new AuthorizationException('Campaign assets must belong to the active Church.');
        }
    }
}
