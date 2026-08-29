<?php

namespace App\Trust\Rights;

use App\Enums\AssetProvenance;
use App\Enums\AssetRightsStatus;
use App\Enums\AssetUse;
use App\Enums\MediaRenditionState;
use App\Models\MediaAsset;
use App\Models\MediaAssetRights;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RecordMediaAssetRights
{
    public function churchDeclared(MediaAsset $asset): MediaAssetRights
    {
        Gate::authorize('update', $asset);

        return $this->record(
            $asset,
            AssetProvenance::ChurchDeclared,
            AssetRightsStatus::Declared,
            AssetRightsPolicy::churchOperationalUses(),
            Auth::id(),
        );
    }

    public function keryonCreated(MediaAsset $asset): MediaAssetRights
    {
        Gate::authorize('update', $asset);

        return $this->record(
            $asset,
            AssetProvenance::KeryonCreated,
            AssetRightsStatus::Declared,
            AssetRightsPolicy::churchOperationalUses(),
            Auth::id(),
        );
    }

    public function restrict(MediaAsset $asset, AssetRightsStatus $status, string $reason): MediaAssetRights
    {
        Gate::authorize('update', $asset);

        if (! in_array($status, [AssetRightsStatus::Restricted, AssetRightsStatus::Disputed, AssetRightsStatus::Withdrawn], true)
            || blank($reason) || mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages(['rights' => 'A supported restrictive state and bounded reason are required.']);
        }

        $rights = $asset->rights()->firstOrCreate([], $this->attributes(
            $asset,
            AssetProvenance::ChurchDeclared,
            AssetRightsStatus::Declared,
            AssetRightsPolicy::churchOperationalUses(),
            Auth::id(),
        ));

        $rights->forceFill([
            'status' => $status,
            'restriction_reason' => $reason,
            'allowed_uses' => [AssetUse::Store->value, AssetUse::InternalUse->value],
        ])->save();

        $asset->publicReferences()->whereNull('deactivated_at')->update([
            'deactivated_at' => now(),
            'updated_at' => now(),
        ]);

        $asset->renditions()
            ->where('state', MediaRenditionState::Active->value)
            ->each(function ($rendition): void {
                Storage::disk($rendition->disk)->delete($rendition->path);
                $rendition->forceFill([
                    'state' => MediaRenditionState::Revoked,
                    'revoked_at' => now(),
                ])->save();
            });

        return $rights->fresh();
    }

    /** @param list<AssetUse> $uses */
    private function record(MediaAsset $asset, AssetProvenance $provenance, AssetRightsStatus $status, array $uses, ?int $actorId): MediaAssetRights
    {
        return $asset->rights()->firstOrCreate([], $this->attributes($asset, $provenance, $status, $uses, $actorId));
    }

    /** @param list<AssetUse> $uses @return array<string, mixed> */
    private function attributes(MediaAsset $asset, AssetProvenance $provenance, AssetRightsStatus $status, array $uses, ?int $actorId): array
    {
        return [
            'church_id' => $asset->church_id,
            'provenance' => $provenance,
            'status' => $status,
            'allowed_uses' => array_map(fn (AssetUse $use): string => $use->value, $uses),
            'attribution_required' => false,
            'declared_by' => $actorId,
            'declared_at' => now(),
        ];
    }
}
