<?php

namespace App\Marketplace;

use App\Enums\MarketplacePublicationStatus;
use App\Marketplace\Entitlements\MarketplaceEntitlement;
use App\Models\MarketplaceAcquisition;
use App\Models\MarketplaceItem;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;

class AcquireMarketplaceItem
{
    public function __construct(
        private readonly MarketplaceEntitlement $entitlements,
        private readonly MarketplaceRightsGate $rights,
    ) {}

    public function handle(MarketplaceItem $item): MarketplaceAcquisition
    {
        Gate::authorize('acquire', $item);
        $membership = app(TenantContext::class)->currentMembership()
            ?? throw new LogicException('A Marketplace acquisition requires an active Church membership.');

        if ($item->trashed() || $item->publication_status !== MarketplacePublicationStatus::PUBLISHED || $item->published_at === null) {
            throw ValidationException::withMessages(['marketplace' => 'This Marketplace item is not available.']);
        }

        $source = $item->currentSourceVersion();

        if ($source === null) {
            throw ValidationException::withMessages(['marketplace' => 'This Marketplace item has no available source package.']);
        }

        if (! $this->rights->permits($source)) {
            throw ValidationException::withMessages(['marketplace' => 'This Marketplace item is not available.']);
        }

        $decision = $this->entitlements->decide($item, $membership);

        if (! $decision->allowed || $decision->basis === null) {
            throw ValidationException::withMessages([
                'marketplace' => $decision->failureCode === 'premium_entitlement_unavailable'
                    ? 'Premium Marketplace acquisition is not available yet.'
                    : 'This Marketplace item is not available to the active Church.',
            ]);
        }

        return DB::transaction(function () use ($item, $source, $membership, $decision): MarketplaceAcquisition {
            $existing = MarketplaceAcquisition::query()
                ->where('marketplace_item_id', $item->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $acquisition = new MarketplaceAcquisition;
            $acquisition->forceFill([
                'church_id' => $membership->church_id,
                'marketplace_item_id' => $item->id,
                'marketplace_source_version_id' => $source->id,
                'access_type' => $item->access_type,
                'acquisition_basis' => $decision->basis,
                'entitlement_reference' => $decision->reference,
                'acquired_by' => Auth::id(),
                'acquired_at' => now(),
            ])->save();

            return $acquisition->fresh(['item', 'sourceVersion']);
        });
    }
}
