<?php

namespace App\Marketplace;

use App\Enums\MarketplaceDownloadOutcome;
use App\Enums\MarketplacePublicationStatus;
use App\Enums\MarketplaceSourceAvailability;
use App\Enums\MarketplaceSourceValidationStatus;
use App\Marketplace\Delivery\MarketplaceDeliveryMechanism;
use App\Marketplace\Delivery\MarketplaceDeliveryResult;
use App\Marketplace\Entitlements\MarketplaceEntitlement;
use App\Models\MarketplaceAcquisition;
use App\Models\MarketplaceDownload;
use App\Models\MarketplaceItem;
use App\Models\MarketplaceSourceVersion;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LogicException;

class MarketplaceSourceDelivery
{
    public function __construct(
        private readonly MarketplaceEntitlement $entitlements,
        private readonly MarketplaceDeliveryMechanism $delivery,
        private readonly MarketplaceRightsGate $rights,
    ) {}

    public function issue(MarketplaceItem $item): MarketplaceDeliveryResult
    {
        Gate::authorize('download', $item);
        $membership = app(TenantContext::class)->currentMembership()
            ?? throw new LogicException('Marketplace delivery requires an active Church membership.');
        $acquisition = MarketplaceAcquisition::query()
            ->with('sourceVersion')
            ->where('marketplace_item_id', $item->id)
            ->first();

        if ($acquisition === null) {
            throw ValidationException::withMessages(['marketplace' => 'Acquire this Marketplace item before downloading it.']);
        }

        $source = $acquisition->sourceVersion;
        $failureCode = $this->failureCode($item, $source);
        $decision = $this->entitlements->decide($item, $membership);

        if (! $decision->allowed) {
            $failureCode = $decision->failureCode ?? 'marketplace_entitlement_denied';
        }

        if ($failureCode !== null) {
            $this->record($acquisition, MarketplaceDownloadOutcome::FAILED, $failureCode);
            throw ValidationException::withMessages(['marketplace' => 'This Marketplace source package is not available for download.']);
        }

        $event = $this->record($acquisition, MarketplaceDownloadOutcome::ISSUED);

        return new MarketplaceDeliveryResult(
            downloadEventId: $event->id,
            sha256: $source->sha256,
            response: $this->delivery->response($source),
        );
    }

    private function failureCode(MarketplaceItem $item, ?MarketplaceSourceVersion $source): ?string
    {
        if ($item->trashed() || $item->publication_status !== MarketplacePublicationStatus::PUBLISHED || $item->published_at === null) {
            return 'marketplace_item_unpublished';
        }

        if ($source === null
            || $source->marketplace_item_id !== $item->id
            || $source->availability_status !== MarketplaceSourceAvailability::AVAILABLE
            || $source->validation_status !== MarketplaceSourceValidationStatus::VALID) {
            return 'marketplace_source_unavailable';
        }

        if (! $this->rights->permits($source)) {
            return 'marketplace_source_rights_unavailable';
        }

        $key = $source->getRawOriginal('storage_key');

        if (! Storage::disk($source->disk)->exists($key)) {
            return 'marketplace_source_missing';
        }

        $bytes = Storage::disk($source->disk)->get($key);

        return is_string($bytes) && hash_equals($source->sha256, hash('sha256', $bytes))
            ? null
            : 'marketplace_source_integrity_failed';
    }

    private function record(MarketplaceAcquisition $acquisition, MarketplaceDownloadOutcome $outcome, ?string $failureCode = null): MarketplaceDownload
    {
        $event = new MarketplaceDownload;
        $event->forceFill([
            'church_id' => $acquisition->church_id,
            'marketplace_acquisition_id' => $acquisition->id,
            'marketplace_source_version_id' => $acquisition->marketplace_source_version_id,
            'user_id' => Auth::id(),
            'outcome' => $outcome,
            'failure_code' => $failureCode,
            'issued_at' => now(),
        ])->save();

        return $event;
    }
}
