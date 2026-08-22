<?php

namespace App\Design\Rendering;

final readonly class ResolvedDesignMedia
{
    public function __construct(
        public int $mediaAssetId,
        public string $disk,
        public string $path,
        public string $mimeType,
        public ?int $width,
        public ?int $height,
    ) {}
}
