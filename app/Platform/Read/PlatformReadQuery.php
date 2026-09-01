<?php

namespace App\Platform\Read;

use App\Enums\PlatformCapability;
use App\Support\PlatformContext;
use Illuminate\Database\Query\Builder;

abstract class PlatformReadQuery
{
    protected function authorize(PlatformCapability $capability): void
    {
        abort_unless(app(PlatformContext::class)->hasCapability($capability), 403);
    }

    protected function prefix(Builder $query, string $column, string $term): void
    {
        $term = trim(mb_strtolower($term));
        if ($term !== '') {
            $query->whereRaw("lower({$column}) like ?", [$term.'%']);
        }
    }

    protected function date(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    protected function maskEmail(?string $email): string
    {
        if (! $email || ! str_contains($email, '@')) {
            return 'Unavailable';
        }

        [$name, $domain] = explode('@', $email, 2);

        return mb_substr($name, 0, 1).str_repeat('*', max(2, min(6, mb_strlen($name) - 1))).'@'.$domain;
    }
}
