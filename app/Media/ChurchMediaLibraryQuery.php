<?php

namespace App\Media;

use App\Enums\AssetProvenance;
use App\Enums\AssetRightsStatus;
use App\Enums\AssetUse;
use App\Models\ChurchBrandProfile;
use App\Models\MediaAsset;
use App\Models\OrganizationCommunicationImportResult;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * K-MEDIA-V1-001B §42-§43 — the single focused read model behind the
 * Church Media Library: tenant-scoped listing (via `MediaAsset`'s own
 * `BelongsToChurch` global scope — no manual `church_id` filter is
 * needed or added, matching the Congregation/Content Studio pattern
 * rather than Organization Communications' deliberately-not-scoped
 * delivery pattern), search, provenance/rights filters, sort, and real
 * SQL pagination. Usage/reference lookups are deliberately kept on this
 * same class rather than a second abstraction (§57's "prefer one focused
 * query/read class") and are only ever computed for a single asset (the
 * Media detail surface), never eagerly joined into the list query
 * (§43 — cards stay cheap).
 */
final class ChurchMediaLibraryQuery
{
    public const SORT_NEWEST = 'newest';

    public const SORT_OLDEST = 'oldest';

    public const SORT_NAME = 'name';

    public function paginate(
        string $search = '',
        ?AssetProvenance $provenance = null,
        ?AssetRightsStatus $rightsStatus = null,
        string $sort = self::SORT_NEWEST,
        int $perPage = 24,
    ): LengthAwarePaginator {
        $query = MediaAsset::query()->with('rights');

        $term = trim($search);
        if ($term !== '') {
            $pattern = $this->pattern($term);
            $query->where(fn (Builder $q) => $q->where('original_filename', 'like', $pattern)->orWhere('alt_text', 'like', $pattern));
        }

        if ($provenance !== null) {
            $query->whereHas('rights', fn (Builder $q) => $q->where('provenance', $provenance->value));
        }

        if ($rightsStatus !== null) {
            $query->whereHas('rights', fn (Builder $q) => $q->where('status', $rightsStatus->value));
        }

        match ($sort) {
            self::SORT_OLDEST => $query->oldest('created_at'),
            self::SORT_NAME => $query->orderBy('original_filename'),
            default => $query->latest('created_at'),
        };

        return $query->paginate(min(max($perPage, 1), 50), pageName: 'mediaPage');
    }

    public function findByUuid(string $uuid): MediaAsset
    {
        return MediaAsset::query()->with('rights')->where('uuid', $uuid)->firstOrFail();
    }

    /** @return array{provenance: string, statusLabel: string, allowedUses: list<string>, attributionRequired: bool, attributionText: ?string} */
    public function rightsSummary(MediaAsset $asset): array
    {
        $rights = $asset->rights;

        if ($rights === null) {
            // K-MEDIA-V1-001A §15 — pre-existing operational media with no
            // rights row yet gets the same narrow historical default
            // `AssetRightsPolicy` itself applies; the Library must present
            // that truthfully, not invent a richer record.
            return [
                'provenance' => $this->provenanceLabel(null),
                'statusLabel' => 'Not yet declared',
                'allowedUses' => array_map(fn (AssetUse $use): string => $this->useLabel($use), [AssetUse::Store, AssetUse::InternalUse, AssetUse::Publish]),
                'attributionRequired' => false,
                'attributionText' => null,
            ];
        }

        return [
            'provenance' => $this->provenanceLabel($rights->provenance, $asset),
            'statusLabel' => $this->statusLabel($rights->status),
            'allowedUses' => collect($rights->allowed_uses ?? [])
                ->map(fn (string $value): string => $this->useLabel(AssetUse::from($value)))
                ->all(),
            'attributionRequired' => (bool) $rights->attribution_required,
            'attributionText' => $rights->attribution_text,
        ];
    }

    public function provenanceLabel(?AssetProvenance $provenance, ?MediaAsset $asset = null): string
    {
        if ($provenance === AssetProvenance::OrganizationShared && $asset !== null) {
            $organizationName = $this->organizationNameFor($asset);

            return $organizationName !== null
                ? "Shared by {$organizationName}"
                : 'Shared by your Organization';
        }

        return match ($provenance) {
            AssetProvenance::ChurchDeclared => 'Uploaded by your Church',
            AssetProvenance::KeryonCreated => 'Created in Design Studio',
            AssetProvenance::KeryonCommissioned => 'Commissioned through Keryon',
            AssetProvenance::LicensedThirdParty => 'Licensed third-party material',
            AssetProvenance::MarketplaceCreator => 'From a Marketplace creator',
            AssetProvenance::AiGenerated => 'AI-generated',
            AssetProvenance::OrganizationShared => 'Shared by your Organization',
            AssetProvenance::Unknown, null => 'Source not recorded',
        };
    }

    /**
     * K-MEDIA-V1-001A §27/§9, K-MEDIA-V1-001B §9/§24 — the Organization's
     * display name only, read through the same bounded, Church-owned
     * import-lineage evidence `OrganizationCommunicationImportResult`
     * already carries (§27 of the discovery report). This never touches
     * Organization storage, Organization asset IDs, or any Organization
     * editing surface, and requires no `OrganizationContext` — it is a
     * single read of a `church_id`-matched historical record the Church
     * already owns, plus the Organization's own `name` column.
     */
    private function organizationNameFor(MediaAsset $asset): ?string
    {
        return OrganizationCommunicationImportResult::query()
            ->where('church_id', $asset->church_id)
            ->where('media_asset_id', $asset->id)
            ->with('import.organization')
            ->first()
            ?->import
            ?->organization
            ?->name;
    }

    private function statusLabel(AssetRightsStatus $status): string
    {
        return match ($status) {
            AssetRightsStatus::Unverified => 'Unverified',
            AssetRightsStatus::Declared => 'Declared',
            AssetRightsStatus::Verified => 'Verified',
            AssetRightsStatus::Restricted => 'Restricted',
            AssetRightsStatus::Expired => 'Expired',
            AssetRightsStatus::Disputed => 'Disputed',
            AssetRightsStatus::Withdrawn => 'Withdrawn',
        };
    }

    private function useLabel(AssetUse $use): string
    {
        return match ($use) {
            AssetUse::Store => 'Store',
            AssetUse::InternalUse => 'Internal use',
            AssetUse::Publish => 'Publish',
            AssetUse::AiProcess => 'AI processing',
            AssetUse::Reference => 'Reference',
            AssetUse::Train => 'AI training',
            AssetUse::CommercialUse => 'Commercial use',
            AssetUse::Redistribute => 'Redistribute',
        };
    }

    /**
     * K-MEDIA-V1-001B §19/§59 — every signal here reuses a relationship
     * that already exists for a different reason (Brand's own FKs,
     * `CampaignMedia`/`DesignMedia`'s deletion-safety associations,
     * `MediaPublicReference`'s Website-publish tracking). No new
     * cross-domain analytics table or join is introduced; an asset with
     * none of these returns an empty list rather than a fabricated
     * "not used anywhere" claim beyond what is actually known.
     *
     * @return list<string>
     */
    public function usageFor(MediaAsset $asset): array
    {
        $usage = [];

        $brand = ChurchBrandProfile::query()
            ->where(fn (Builder $q) => $q->where('primary_logo_media_id', $asset->id)->orWhere('mark_media_id', $asset->id))
            ->first();

        if ($brand !== null) {
            $usage[] = $brand->primary_logo_media_id === $asset->id ? 'Used as your primary logo' : 'Used as your Brand mark';
        }

        $campaignCount = $asset->campaignAssociations()->count();
        if ($campaignCount > 0) {
            $usage[] = $campaignCount === 1 ? 'Used in 1 campaign' : "Used in {$campaignCount} campaigns";
        }

        if ($asset->designSelections()->exists() || $asset->generatedDesignOutputs()->exists()) {
            $usage[] = 'Used in Design Studio';
        }

        $publiclyReferenced = $asset->publicReferences()->whereNull('deactivated_at')->exists();
        if ($publiclyReferenced) {
            $usage[] = 'Published on your Website';
        }

        return $usage;
    }

    private function pattern(string $term): string
    {
        return '%'.addcslashes($term, '\\%_').'%';
    }
}
