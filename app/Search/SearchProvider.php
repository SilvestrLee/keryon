<?php

namespace App\Search;

use App\Enums\WorkspaceType;

interface SearchProvider
{
    public function supports(WorkspaceType $workspace): bool;

    public function eligible(): bool;

    /** @return list<SearchResult> */
    public function search(string $term, int $limit): array;
}
