<?php

namespace App\ChurchStaff;

use App\Enums\ChurchRole;
use DomainException;

final class RoleSet
{
    /** @param list<ChurchRole|string> $roles @return list<ChurchRole> */
    public static function normalize(array $roles): array
    {
        $normalized = collect($roles)->map(function (ChurchRole|string $role): ChurchRole {
            if ($role instanceof ChurchRole) {
                return $role;
            }
            $resolved = ChurchRole::tryFrom($role);
            if (! $resolved) {
                throw new DomainException('Only governed Church roles may be assigned.');
            }

            return $resolved;
        })->unique(fn (ChurchRole $role) => $role->value)->values()->all();
        if ($normalized === []) {
            throw new DomainException('At least one Church role is required.');
        }

        return $normalized;
    }

    /** @param list<ChurchRole> $roles @return list<string> */
    public static function values(array $roles): array
    {
        return collect($roles)->map(fn (ChurchRole $role) => $role->value)->sort()->values()->all();
    }
}
