<?php

namespace App\Design\Rendering;

final readonly class DesignRenderingContext
{
    /**
     * @param  array<string, string>  $inputs
     * @param  array<string, scalar|null>  $brand
     * @param  array<string, ResolvedDesignMedia>  $media
     */
    public function __construct(
        public int $designId,
        public int $churchId,
        public string $churchName,
        public string $templateKey,
        public int $templateVersion,
        public string $variant,
        public array $inputs,
        public array $brand,
        public array $media,
        public ?ResolvedDesignMedia $primaryLogo,
        public ?ResolvedDesignMedia $mark,
    ) {}
}
