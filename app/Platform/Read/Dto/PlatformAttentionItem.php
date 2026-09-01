<?php

namespace App\Platform\Read\Dto;

use App\Enums\PlatformCapability;

final readonly class PlatformAttentionItem
{
    public function __construct(
        public string $category, public string $severity, public string $reason,
        public string $targetType, public string $targetId, public string $title,
        public ?string $firstAt, public ?string $lastAt, public string $url,
        public PlatformCapability $capability,
    ) {}
}
