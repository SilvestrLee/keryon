<?php

namespace App\Design\Templates;

use App\Enums\DesignImageFit;

final readonly class DesignImageSlot
{
    public function __construct(
        public string $key,
        public string $label,
        public bool $required,
        public DesignImageFit $fit,
        public int $minimumWidth,
        public int $minimumHeight,
    ) {}
}
