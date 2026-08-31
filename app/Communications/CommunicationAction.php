<?php

namespace App\Communications;

final readonly class CommunicationAction
{
    public function __construct(
        public string $key,
        public string $title,
        public string $description,
        public string $destination,
        public string $label,
        public string $category,
        public ?int $count = null,
    ) {}
}
