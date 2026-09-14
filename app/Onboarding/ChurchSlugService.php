<?php

namespace App\Onboarding;

use App\Models\Church;
use DomainException;
use Illuminate\Support\Str;

class ChurchSlugService
{
    public function available(string $source, bool $explicit = false): string
    {
        $base = Str::slug(Str::lower(trim($source)));
        if ($base === '' || strlen($base) > 63 && $explicit) {
            throw new DomainException('The requested Church slug is not a valid hostname label.');
        }
        $base = substr($base, 0, 63);
        if (in_array($base, $this->reserved(), true)) {
            if ($explicit) {
                throw new DomainException('The requested Church slug is reserved.');
            }
            $base .= '-church';
        }
        if (! preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $base)) {
            throw new DomainException('The requested Church slug is not a valid hostname label.');
        }

        for ($suffix = 1; $suffix <= 1000; $suffix++) {
            $candidate = $suffix === 1 ? $base : substr($base, 0, 63 - strlen('-'.$suffix)).'-'.$suffix;
            if (! Church::query()->where('slug', $candidate)->exists()) {
                return $candidate;
            }
        }
        throw new DomainException('Unable to allocate a unique Church slug.');
    }

    /**
     * K-DOMAIN-001F §15 — config('public-website.reserved_subdomains') is
     * the single canonical reserved-label source; do not duplicate it here.
     *
     * @return list<string>
     */
    private function reserved(): array
    {
        return array_map('strtolower', config('public-website.reserved_subdomains', []));
    }
}
