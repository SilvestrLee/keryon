<?php

namespace App\PublicWebsite;

use App\Models\Church;
use App\Models\ChurchDomain;

final class CanonicalChurchWebsiteUrl
{
    /** @var array<int,string> */
    private array $baseUrls = [];

    public function __construct(private PublicWebsiteUrl $firstParty) {}

    public function page(Church $church, string $page = 'home'): string
    {
        $path = $page === 'home' ? '' : '/'.ltrim($page, '/');
        $base = $this->baseUrls[$church->getKey()] ??= $this->resolveBase($church);

        return $base.$path;
    }

    public function path(Church $church, string $path): string
    {
        $normalized = '/'.ltrim($path, '/');
        $base = rtrim($this->page($church), '/');

        return $base.($normalized === '/' ? '' : $normalized);
    }

    private function resolveBase(Church $church): string
    {
        $domain = ChurchDomain::withoutGlobalScope('church_tenant')
            ->where('church_id', $church->getKey())
            ->where('is_primary', true)
            ->eligible()
            ->first();

        return $domain === null ? $this->firstParty->page($church) : 'https://'.$domain->normalized_hostname;
    }
}
