<?php

namespace App\Dashboard;

final readonly class DashboardMetric
{
    public function __construct(
        public string $key,
        public string $label,
        public int|string $value,
        public ?string $description = null,
        public ?string $destination = null,
        public ?string $actionLabel = null,
    ) {}

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
