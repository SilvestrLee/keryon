<?php

namespace App\PublicWebsite;

use App\Enums\Capability;
use App\Enums\MediaPublicConsumer;
use App\Enums\PublicationDestination;
use App\Media\PublicMediaRenditionManager;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\MediaAsset;
use App\Models\MediaPublicReference;
use App\Models\WebsiteContentProvenance;
use App\Models\WebsitePublication;
use App\Models\WebsitePublicationProvenance;
use App\Models\WebsiteSettings;
use App\Support\TenantContext;
use App\Trust\Publishing\PublicationTrustContext;
use App\Trust\Publishing\PublicationTrustGate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class WebsitePublisher
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly WebsiteSnapshot $snapshots,
        private readonly PublicMediaRenditionManager $renditions,
        private readonly PublicationTrustGate $trust,
    ) {}

    public function publish(): WebsitePublication
    {
        $membership = $this->authorizedMembership();
        $church = Church::query()->findOrFail($membership->church_id);

        $settings = WebsiteSettings::query()->firstOrFail();
        $snapshot = $this->snapshots->capture($church, $settings);
        $assetIds = array_filter($this->mediaUsages($snapshot), fn (?int $assetId): bool => $assetId !== null);
        $publicMedia = [];
        $prepared = [];

        foreach ($assetIds as $usage => $assetId) {
            $asset = MediaAsset::withoutGlobalScopes()->where('church_id', $church->id)->findOrFail($assetId);
            $rendition = $this->renditions->rendition($asset, $church->id);
            $publicMedia[$usage] = $rendition->uuid;
            $prepared[$usage] = $rendition;
        }

        $snapshot['public_media'] = $publicMedia;
        $snapshot = $this->withoutPrivateMediaIds($snapshot);

        return DB::transaction(function () use ($membership, $church, $snapshot, $assetIds, $publicMedia, $prepared): WebsitePublication {
            $settings = WebsiteSettings::query()->lockForUpdate()->firstOrFail();
            $previousPublicationId = $settings->current_publication_id;
            $theme = (string) $settings->getRawOriginal('theme');
            $decision = $this->trust->evaluate(new PublicationTrustContext(
                PublicationDestination::ChurchWebsite,
                $membership,
                $church,
                $snapshot,
                $assetIds,
                $publicMedia,
            ));

            $publication = WebsitePublication::query()->create([
                'destination' => PublicationDestination::ChurchWebsite,
                'theme' => $theme,
                'snapshot' => $snapshot,
                'working_fingerprint' => $this->snapshots->fingerprint($snapshot, $theme),
                'previous_publication_id' => $previousPublicationId,
                'trust_evidence' => $decision->evidence,
                'published_by' => $membership->user_id,
                'published_at' => now(),
            ]);

            $this->attributeCurrentWebsiteLineage($publication, $church->id);

            foreach ($prepared as $usage => $rendition) {
                $this->renditions->reference($rendition, $church->id, $publication->id, $usage);
            }

            if ($previousPublicationId !== null) {
                MediaPublicReference::withoutGlobalScopes()
                    ->where('consumer_type', MediaPublicConsumer::WebsitePublication->value)
                    ->where('consumer_id', $previousPublicationId)
                    ->whereNull('deactivated_at')
                    ->update(['deactivated_at' => now(), 'updated_at' => now()]);
            }

            $settings->update(['current_publication_id' => $publication->id]);

            return $publication;
        });
    }

    private function attributeCurrentWebsiteLineage(WebsitePublication $publication, int $churchId): void
    {
        $provenanceIds = WebsiteContentProvenance::query()
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('website_content_provenances as newer')
                    ->whereColumn('newer.church_id', 'website_content_provenances.church_id')
                    ->whereColumn('newer.destination', 'website_content_provenances.destination')
                    ->whereColumn('newer.website_record_id', 'website_content_provenances.website_record_id')
                    ->whereColumn('newer.id', '>', 'website_content_provenances.id');
            })
            ->lockForUpdate()
            ->pluck('id');

        foreach ($provenanceIds as $provenanceId) {
            WebsitePublicationProvenance::query()->firstOrCreate([
                'church_id' => $churchId,
                'website_publication_id' => $publication->id,
                'website_content_provenance_id' => $provenanceId,
            ]);
        }
    }

    public function unpublish(): void
    {
        $this->authorizedMembership();

        DB::transaction(function (): void {
            $settings = WebsiteSettings::query()->lockForUpdate()->firstOrFail();

            if ($settings->current_publication_id !== null) {
                MediaPublicReference::withoutGlobalScopes()
                    ->where('consumer_type', MediaPublicConsumer::WebsitePublication->value)
                    ->where('consumer_id', $settings->current_publication_id)
                    ->whereNull('deactivated_at')
                    ->update(['deactivated_at' => now(), 'updated_at' => now()]);
            }

            $settings->update(['current_publication_id' => null]);
        });
    }

    /** @return array<string, int|null> */
    private function mediaUsages(array $snapshot): array
    {
        $usages = [
            'brand.logo' => $snapshot['brand']['primary_logo_media_id'] ?? null,
            'brand.mark' => $snapshot['brand']['mark_media_id'] ?? null,
            'home.hero' => $snapshot['home']['hero_image_id'] ?? null,
        ];

        foreach ($snapshot['leadership'] ?? [] as $index => $profile) {
            $usages["leadership.{$index}.photo"] = $profile['photo_id'] ?? null;
        }

        foreach ($snapshot['ministries'] ?? [] as $index => $ministry) {
            $usages["ministries.{$index}.image"] = $ministry['image_id'] ?? null;
        }

        return $usages;
    }

    /** @param array<string, mixed> $snapshot @return array<string, mixed> */
    private function withoutPrivateMediaIds(array $snapshot): array
    {
        unset($snapshot['brand']['primary_logo_media_id'], $snapshot['brand']['mark_media_id']);
        unset($snapshot['home']['hero_image_id']);

        foreach ($snapshot['leadership'] ?? [] as $index => $_profile) {
            unset($snapshot['leadership'][$index]['photo_id']);
        }

        foreach ($snapshot['ministries'] ?? [] as $index => $_ministry) {
            unset($snapshot['ministries'][$index]['image_id']);
        }

        return $snapshot;
    }

    private function authorizedMembership(): ChurchMembership
    {
        $membership = $this->tenant->currentMembership();

        if ($membership === null || ! $membership->hasCapability(Capability::WebsitePublish)) {
            throw new AuthorizationException('You are not authorized to publish this Website.');
        }

        return $membership;
    }
}
