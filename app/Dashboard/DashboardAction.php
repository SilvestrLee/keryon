<?php

namespace App\Dashboard;

final readonly class DashboardAction
{
    public function __construct(
        public string $key,
        public int $priority,
        public string $title,
        public string $description,
        public string $destination,
        public string $category,
        public ?int $count = null,
        public string $label = 'Open',
    ) {}

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
