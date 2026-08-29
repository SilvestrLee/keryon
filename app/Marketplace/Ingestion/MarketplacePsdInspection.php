<?php

namespace App\Marketplace\Ingestion;

final readonly class MarketplacePsdInspection
{
    public function __construct(
        public string $path,
        public string $originalFilename,
        public int $size,
        public string $sha256,
        public int $version,
        public int $channels,
        public int $width,
        public int $height,
        public int $depth,
        public int $colorMode,
    ) {}

    /** @return array<string, int|string> */
    public function metadata(): array
    {
        return [
            'signature' => '8BPS',
            'version' => $this->version,
            'channels' => $this->channels,
            'width' => $this->width,
            'height' => $this->height,
            'depth' => $this->depth,
            'color_mode' => $this->colorMode,
            'size' => $this->size,
            'sha256' => $this->sha256,
            'original_filename' => $this->originalFilename,
        ];
    }
}
