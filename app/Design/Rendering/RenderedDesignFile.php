<?php

namespace App\Design\Rendering;

final readonly class RenderedDesignFile
{
    public function __construct(
        public string $bytes,
        public string $mimeType,
        public string $extension,
        public int $width,
        public int $height,
    ) {}
}
