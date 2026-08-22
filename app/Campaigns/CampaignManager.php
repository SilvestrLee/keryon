<?php

namespace App\Campaigns;

use App\Enums\CampaignStatus;
use App\Enums\Capability;
use App\Models\Campaign;
use App\Models\ChurchMembership;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use LogicException;

class CampaignManager
{
    public function __construct(private readonly TenantContext $tenant) {}

    /** @param array{title: string, purpose?: ?string, starts_on?: mixed, ends_on?: mixed} $attributes */
    public function create(array $attributes): Campaign
    {
        $membership = $this->authorizedMembership();

        $campaign = new Campaign($attributes);
        $campaign->status = CampaignStatus::DRAFT;
        $campaign->forceFill([
            'church_id' => $membership->church_id,
            'created_by' => $membership->user_id,
            'updated_by' => $membership->user_id,
        ]);
        $campaign->save();

        return $campaign;
    }

    /** @param array{title?: string, purpose?: ?string, starts_on?: mixed, ends_on?: mixed} $attributes */
    public function update(Campaign $campaign, array $attributes): Campaign
    {
        $membership = $this->authorizedFor($campaign);

        $campaign->fill($attributes);
        $campaign->forceFill(['updated_by' => $membership->user_id]);
        $campaign->save();

        return $campaign;
    }

    public function transition(Campaign $campaign, CampaignStatus $status, bool $confirmed = false): Campaign
    {
        $membership = $this->authorizedFor($campaign);

        if ($campaign->status === CampaignStatus::ACTIVE && $status === CampaignStatus::PLANNED && ! $confirmed) {
            throw new LogicException('Returning an active Campaign to planned requires deliberate confirmation.');
        }

        $campaign->transitionTo($status);
        $campaign->forceFill(['updated_by' => $membership->user_id]);
        $campaign->save();

        return $campaign;
    }

    public function delete(Campaign $campaign): void
    {
        $this->authorizedFor($campaign);

        if ($campaign->status !== CampaignStatus::DRAFT) {
            throw new LogicException('Only a draft Campaign may be deleted; complete and archive historical Campaigns instead.');
        }

        DB::transaction(function () use ($campaign): void {
            $campaign->communications()->get()->each->delete();
            $campaign->mediaAssociations()->delete();
            $campaign->delete();
        });
    }

    private function authorizedFor(Campaign $campaign): ChurchMembership
    {
        $membership = $this->authorizedMembership();

        if ($campaign->church_id !== $membership->church_id) {
            throw new AuthorizationException('The Campaign does not belong to the active Church.');
        }

        return $membership;
    }

    private function authorizedMembership(): ChurchMembership
    {
        $membership = $this->tenant->currentMembership();

        if ($membership === null || ! $membership->hasCapability(Capability::CampaignsManage)) {
            throw new AuthorizationException('You are not authorized to manage Campaigns.');
        }

        return $membership;
    }
}
