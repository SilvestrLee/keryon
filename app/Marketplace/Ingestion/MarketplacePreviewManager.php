<?php

namespace App\Marketplace\Ingestion;

use App\Enums\MarketplacePreviewType;
use App\Models\MarketplaceItem;
use App\Models\MarketplacePreview;
use App\Models\MarketplaceSourceVersion;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MarketplacePreviewManager
{
    /** @return array{thumbnail: MarketplacePreview, preview: MarketplacePreview} */
    public function register(MarketplaceItem $item, MarketplaceSourceVersion $source, string $path, string $altText): array
    {
        if ($source->marketplace_item_id !== $item->id) {
            throw ValidationException::withMessages(['preview' => 'The Marketplace preview source does not belong to the item.']);
        }

        if ($existing = $source->previews()->whereIn('type', [MarketplacePreviewType::THUMBNAIL->value, MarketplacePreviewType::PREVIEW->value])->get()->keyBy(fn (MarketplacePreview $preview) => $preview->type->value)) {
            if ($existing->has(MarketplacePreviewType::THUMBNAIL->value) && $existing->has(MarketplacePreviewType::PREVIEW->value)) {
                return ['thumbnail' => $existing[MarketplacePreviewType::THUMBNAIL->value], 'preview' => $existing[MarketplacePreviewType::PREVIEW->value]];
            }
        }

        $bytes = is_file($path) ? file_get_contents($path) : false;
        $image = is_string($bytes) ? @imagecreatefromstring($bytes) : false;

        if (! is_string($bytes) || $bytes === '' || $image === false) {
            throw ValidationException::withMessages(['preview' => 'A readable JPEG or PNG Marketplace preview is required.']);
        }

        $width = imagesx($image);
        $height = imagesy($image);

        if ($width < 320 || $height < 320 || $width > 5000 || $height > 5000 || strlen($bytes) > 20_000_000) {
            imagedestroy($image);
            throw ValidationException::withMessages(['preview' => 'The Marketplace preview dimensions or size are outside permitted limits.']);
        }

        $previewBytes = $this->png($image);
        $thumbnailImage = imagecreatetruecolor(480, 480);

        if ($thumbnailImage !== false) {
            imagealphablending($thumbnailImage, false);
            imagesavealpha($thumbnailImage, true);
            imagecopyresampled($thumbnailImage, $image, 0, 0, 0, 0, 480, 480, $width, $height);
        }
        imagedestroy($image);

        if ($thumbnailImage === false) {
            throw ValidationException::withMessages(['preview' => 'The Marketplace thumbnail could not be generated.']);
        }

        $thumbnailBytes = $this->png($thumbnailImage);
        imagedestroy($thumbnailImage);

        return [
            'thumbnail' => $this->store($item, $source, MarketplacePreviewType::THUMBNAIL, $thumbnailBytes, 480, 480, $altText),
            'preview' => $this->store($item, $source, MarketplacePreviewType::PREVIEW, $previewBytes, $width, $height, $altText),
        ];
    }

    private function png(\GdImage $image): string
    {
        ob_start();
        imagepng($image, null, 9);
        $bytes = ob_get_clean();

        if (! is_string($bytes) || $bytes === '') {
            throw ValidationException::withMessages(['preview' => 'The Marketplace preview could not be encoded.']);
        }

        return $bytes;
    }

    private function store(MarketplaceItem $item, MarketplaceSourceVersion $source, MarketplacePreviewType $type, string $bytes, int $width, int $height, string $altText): MarketplacePreview
    {
        $disk = config('marketplace.preview_disk', 'marketplace');
        $key = 'marketplace/previews/'.Str::uuid().'/'.$type->value.'.png';

        if (! Storage::disk($disk)->put($key, $bytes)) {
            throw ValidationException::withMessages(['preview' => 'The Marketplace preview could not be stored.']);
        }

        $preview = new MarketplacePreview(['type' => $type, 'sort_order' => $type === MarketplacePreviewType::THUMBNAIL ? 0 : 1, 'alt_text' => $altText]);
        $preview->forceFill([
            'marketplace_item_id' => $item->id,
            'marketplace_source_version_id' => $source->id,
            'disk' => $disk,
            'storage_key' => $key,
            'mime_type' => 'image/png',
            'size' => strlen($bytes),
            'width' => $width,
            'height' => $height,
            'sha256' => hash('sha256', $bytes),
        ])->save();

        return $preview;
    }
}
