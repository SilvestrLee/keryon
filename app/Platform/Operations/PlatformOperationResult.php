<?php

namespace App\Platform\Operations;

final readonly class PlatformOperationResult
{
    /** @param array<string, scalar|null> $state */
    public function __construct(
        public string $message,
        public string $targetType,
        public int $targetId,
        public array $state,
        public bool $changed,
    ) {}
}
