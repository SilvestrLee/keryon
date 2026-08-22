<?php

namespace App\Design\Rendering;

use App\Design\Templates\DesignTemplateRegistry;
use App\Models\Design;
use App\Models\MediaAsset;
use App\Support\TenantContext;
use LogicException;

class DesignRenderingContextFactory
{
    public function __construct(private readonly DesignTemplateRegistry $templates) {}

    public function forDesign(Design $design): DesignRenderingContext
    {
        $churchId = app(TenantContext::class)->currentChurchId();

        if ($churchId === null || $design->church_id !== $churchId) {
            throw new LogicException('Design rendering context must remain within the active Church.');
        }

        $this->templates->resolve($design->template_key, $design->template_version);
        $design->loadMissing(['church', 'mediaSelections.mediaAsset', 'primaryLogo', 'mark']);
        $media = [];

        foreach ($design->mediaSelections->sortBy('slot_key') as $selection) {
            $asset = $selection->mediaAsset;

            if ($asset === null || $asset->church_id !== $churchId) {
                throw new LogicException('Design rendering media must remain within the active Church.');
            }

            $media[$selection->slot_key] = new ResolvedDesignMedia(
                mediaAssetId: $asset->id,
                disk: $asset->disk,
                path: $asset->path,
                mimeType: $asset->mime_type,
                width: $asset->width,
                height: $asset->height,
            );
        }

        $inputs = $design->inputs;
        $brand = $design->brand_snapshot;
        ksort($inputs);
        ksort($brand);

        return new DesignRenderingContext(
            designId: $design->id,
            churchId: $design->church_id,
            churchName: $design->church->name,
            templateKey: $design->template_key,
            templateVersion: $design->template_version,
            variant: $design->variant,
            inputs: $inputs,
            brand: $brand,
            media: $media,
            primaryLogo: $this->resolvedAsset($design->primaryLogo, $churchId),
            mark: $this->resolvedAsset($design->mark, $churchId),
        );
    }

    private function resolvedAsset(?MediaAsset $asset, int $churchId): ?ResolvedDesignMedia
    {
        if ($asset === null) {
            return null;
        }

        if ($asset->church_id !== $churchId) {
            throw new LogicException('Design brand media must remain within the active Church.');
        }

        return new ResolvedDesignMedia(
            mediaAssetId: $asset->id,
            disk: $asset->disk,
            path: $asset->path,
            mimeType: $asset->mime_type,
            width: $asset->width,
            height: $asset->height,
        );
    }
}
