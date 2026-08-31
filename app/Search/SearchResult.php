<?php

namespace App\Search;

final readonly class SearchResult
{
    public function __construct(
        public string $group,
        public string $type,
        public string $title,
        public ?string $description,
        public string $url,
    ) {}
}
