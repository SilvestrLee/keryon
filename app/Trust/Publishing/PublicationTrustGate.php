<?php

namespace App\Trust\Publishing;

use App\Enums\AssetUse;
use App\Enums\Capability;
use App\Enums\MediaRenditionState;
use App\Enums\PublicationAiReviewStatus;
use App\Enums\PublicationDestination;
use App\Models\MediaAsset;
use App\Models\MediaRendition;
use App\Trust\Rights\AssetRightsPolicy;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PublicationTrustGate
{
    public function __construct(
        private readonly AssetRightsPolicy $rights,
        private readonly WebsitePublicSchema $schema,
    ) {}

    public function evaluate(PublicationTrustContext $context): PublicationTrustDecision
    {
        if ($context->destination !== PublicationDestination::ChurchWebsite
            || $context->membership->church_id !== $context->church->id
            || ! $context->membership->hasCapability(Capability::WebsitePublish)) {
            $this->deny('The Website publication context is not authorized.');
        }

        if ($context->aiReviewStatus === PublicationAiReviewStatus::Unreviewed) {
            $this->deny('AI-assisted content requires human review before publication.');
        }

        $this->schema->ensure($context->snapshot);

        $assetUsages = array_keys($context->assetIds);
        $renditionUsages = array_keys($context->renditionUuids);
        $snapshotUsages = array_keys($context->snapshot['public_media'] ?? []);
        sort($assetUsages);
        sort($renditionUsages);
        sort($snapshotUsages);

        if ($assetUsages !== $renditionUsages
            || $renditionUsages !== $snapshotUsages
            || ($context->snapshot['public_media'] ?? []) !== $context->renditionUuids) {
            $this->deny('The Website publication media evidence is incomplete.');
        }

        foreach ($context->assetIds as $usage => $assetId) {
            $asset = MediaAsset::withoutGlobalScopes()->where('church_id', $context->church->id)->find($assetId);
            $renditionUuid = $context->renditionUuids[$usage] ?? null;
            $rendition = is_string($renditionUuid)
                ? MediaRendition::withoutGlobalScopes()->where('uuid', $renditionUuid)->first()
                : null;

            if ($asset === null
                || ! $this->rights->allows($asset, AssetUse::Publish)
                || $rendition === null
                || $rendition->church_id !== $context->church->id
                || $rendition->media_asset_id !== $asset->id
                || $rendition->state !== MediaRenditionState::Active
                || ! Storage::disk($rendition->disk)->exists($rendition->path)) {
                $this->deny('A required Website asset cannot be published.');
            }
        }

        return new PublicationTrustDecision([
            'version' => 1,
            'destination' => $context->destination->value,
            'explicit_public_intent' => true,
            'public_schema' => 'church_website_v1',
            'content_authority_basis' => 'authorized_publisher_action',
            'asset_rights_evaluation' => 'passed',
            'ai_review_status' => $context->aiReviewStatus->value,
            'renditions' => $context->renditionUuids,
            'evaluated_at' => now()->toISOString(),
        ]);
    }

    private function deny(string $message): never
    {
        throw ValidationException::withMessages(['publication' => $message]);
    }
}
