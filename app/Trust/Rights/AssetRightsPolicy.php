<?php

namespace App\Trust\Rights;

use App\Enums\AssetRightsStatus;
use App\Enums\AssetUse;
use App\Marketplace\MarketplaceRightsGate;
use App\Models\MarketplaceSourceVersion;
use App\Models\MediaAsset;
use Illuminate\Validation\ValidationException;

class AssetRightsPolicy
{
    public function __construct(private readonly MarketplaceRightsGate $marketplace) {}

    public function allows(MediaAsset|MarketplaceSourceVersion $asset, AssetUse $use): bool
    {
        if ($asset instanceof MarketplaceSourceVersion) {
            return in_array($use, [AssetUse::Publish, AssetUse::CommercialUse, AssetUse::Redistribute], true)
                && $this->marketplace->permits($asset);
        }

        $rights = $asset->rights;

        // Existing Church operational media predates this rights record. It
        // receives only the narrow historical operational default. No AI,
        // reference, training, commercial or redistribution right is inferred.
        if ($rights === null) {
            return in_array($use, self::churchOperationalUses(), true);
        }

        if (in_array($rights->status, [
            AssetRightsStatus::Restricted,
            AssetRightsStatus::Expired,
            AssetRightsStatus::Disputed,
            AssetRightsStatus::Withdrawn,
        ], true)) {
            return in_array($use, [AssetUse::Store, AssetUse::InternalUse], true);
        }

        if ($rights->expires_at !== null && $rights->expires_at->isPast()) {
            return in_array($use, [AssetUse::Store, AssetUse::InternalUse], true);
        }

        return in_array($use->value, $rights->allowed_uses ?? [], true);
    }

    public function ensure(MediaAsset|MarketplaceSourceVersion $asset, AssetUse $use): void
    {
        if (! $this->allows($asset, $use)) {
            throw ValidationException::withMessages([
                'media' => 'This asset is not available for the requested use.',
            ]);
        }
    }

    /** @return list<AssetUse> */
    public static function churchOperationalUses(): array
    {
        return [AssetUse::Store, AssetUse::InternalUse, AssetUse::Publish];
    }
}
