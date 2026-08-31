<?php

namespace App\Website\Drafts;

use App\Commercial\Entitlements\EntitlementResolver;
use App\Enums\AssetUse;
use App\Enums\Capability;
use App\Enums\CommunicationChannel;
use App\Enums\ContentStatus;
use App\Enums\DesignState;
use App\Enums\EntitlementKey;
use App\Enums\WebsiteDraftDestination;
use App\Models\CampaignCommunication;
use App\Models\Church;
use App\Models\ContentItem;
use App\Models\Design;
use App\Models\MediaAsset;
use App\Models\WebsiteContentProvenance;
use App\Support\TenantContext;
use App\Trust\Rights\AssetRightsPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ApplyApprovedContentToWebsiteDraft
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly EntitlementResolver $entitlements,
        private readonly AvailableWebsiteDraftDestinations $destinations,
        private readonly WebsiteDraftMapper $mapper,
        private readonly WebsiteDraftFingerprints $fingerprints,
        private readonly AssetRightsPolicy $rights,
    ) {}

    public function apply(
        ContentItem $source,
        WebsiteDraftDestination $destination,
        ?string $expectedDestinationFingerprint = null,
        bool $replace = false,
        ?CampaignCommunication $communication = null,
        ?MediaAsset $media = null,
    ): WebsiteDraftHandoffResult {
        $membership = $this->tenant->currentMembership();
        $church = $this->tenant->currentChurch();

        if ($membership === null || $church === null || $membership->church_id !== $church->id) {
            throw new AuthorizationException('A valid Church workspace is required.');
        }
        Gate::authorize('view', $source);
        if (! $membership->hasCapability(Capability::WebsiteContentManage)) {
            throw new AuthorizationException('You are not authorized to prepare Website content.');
        }
        if (! $this->entitlements->allows($church, EntitlementKey::WebsiteEnabled)) {
            throw new AuthorizationException('Website is not available for this Church.');
        }

        return DB::transaction(function () use ($source, $destination, $expectedDestinationFingerprint, $replace, $communication, $media, $church, $membership): WebsiteDraftHandoffResult {
            Church::query()->whereKey($church->id)->lockForUpdate()->firstOrFail();
            $source = ContentItem::withoutGlobalScopes()->where('church_id', $church->id)->whereKey($source->id)->lockForUpdate()->firstOrFail();

            if ($source->status !== ContentStatus::APPROVED || $source->approved_at === null) {
                $this->deny('source', 'Only approved Content can be applied to a Website draft.');
            }
            if (! $this->destinations->supports($source->content_type, $destination)) {
                $this->deny('destination', 'This Content type is not compatible with the selected Website destination.');
            }

            [$campaignId, $communicationId] = $this->campaignContext($communication, $source, $church->id);
            [$media, $designId] = $this->mediaContext($media, $source, $communicationId, $destination, $church->id);
            $mapped = $this->mapper->map($source, $destination, $media);
            $sourceFingerprint = $this->fingerprints->source($source, $mapped);
            $model = $destination->modelClass();
            $record = $model::query()->firstOrCreate([]);
            $record = $model::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();
            $before = $this->fingerprints->destination($record, $destination, $this->mapper);
            $latest = WebsiteContentProvenance::query()
                ->where('destination', $destination->value)
                ->where('website_record_id', $record->id)
                ->latest('id')->lockForUpdate()->first();

            if ($latest?->source_fingerprint === $sourceFingerprint && hash_equals($latest->destination_after_fingerprint, $before)) {
                return new WebsiteDraftHandoffResult($destination, $latest, true);
            }
            if ($latest !== null && ! hash_equals($latest->destination_after_fingerprint, $before)) {
                $this->deny('destination', 'This Website draft has changed since communication content was applied. Review it before replacing anything.');
            }
            if ($expectedDestinationFingerprint !== null && ! hash_equals($before, $expectedDestinationFingerprint)) {
                $this->deny('destination', 'This Website draft changed while you were reviewing it. Refresh and try again.');
            }
            if (! $this->fingerprints->empty($destination, $this->mapper, $record) && ! $replace) {
                $this->deny('destination', 'This destination already has content. Confirm replacement before applying.');
            }

            $record->update($mapped);
            $after = $this->fingerprints->destination($record->fresh(), $destination, $this->mapper);
            $provenance = WebsiteContentProvenance::query()->create([
                'church_id' => $church->id,
                'destination' => $destination,
                'website_record_id' => $record->id,
                'content_item_id' => $source->id,
                'campaign_id' => $campaignId,
                'campaign_communication_id' => $communicationId,
                'design_id' => $designId,
                'media_asset_id' => $media?->id,
                'source_fingerprint' => $sourceFingerprint,
                'destination_before_fingerprint' => $before,
                'destination_after_fingerprint' => $after,
                'source_approved_at' => $source->approved_at,
                'applied_by' => $membership->user_id,
                'applied_at' => now(),
            ]);

            return new WebsiteDraftHandoffResult($destination, $provenance, false);
        }, 3);
    }

    /** @return array{int|null, int|null} */
    private function campaignContext(?CampaignCommunication $communication, ContentItem $source, int $churchId): array
    {
        if ($communication === null) {
            return [null, null];
        }
        $communication = CampaignCommunication::withoutGlobalScopes()->where('church_id', $churchId)->whereKey($communication->id)->lockForUpdate()->firstOrFail();
        if ($communication->content_item_id !== $source->id || $communication->channel !== CommunicationChannel::WEBSITE) {
            $this->deny('communication', 'The Website communication does not reference this Content.');
        }

        return [$communication->campaign_id, $communication->id];
    }

    /** @return array{MediaAsset|null, int|null} */
    private function mediaContext(?MediaAsset $media, ContentItem $source, ?int $communicationId, WebsiteDraftDestination $destination, int $churchId): array
    {
        if ($media === null) {
            return [null, null];
        }
        if (! $destination->supportsMedia()) {
            $this->deny('media', 'This Website destination does not accept Media.');
        }
        $media = MediaAsset::withoutGlobalScopes()->where('church_id', $churchId)->whereKey($media->id)->firstOrFail();
        $design = Design::withoutGlobalScopes()->where('church_id', $churchId)->where('state', DesignState::APPROVED->value)
            ->where(fn ($query) => $query->where('content_item_id', $source->id)->when($communicationId, fn ($q) => $q->orWhere('campaign_communication_id', $communicationId)))
            ->whereHas('outputs', fn ($query) => $query->where('media_asset_id', $media->id))->first();
        if ($design === null) {
            $this->deny('media', 'Only approved Design output linked to this communication can be used.');
        }
        $this->rights->ensure($media, AssetUse::Publish);

        return [$media, $design->id];
    }

    private function deny(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
