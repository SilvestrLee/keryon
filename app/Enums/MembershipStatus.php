<?php

namespace App\Enums;

enum MembershipStatus: string
{
    case INVITED = 'invited';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case REMOVED = 'removed';

    public function label(): string
    {
        return match ($this) {
            self::INVITED => 'Invited',
            self::ACTIVE => 'Active',
            self::SUSPENDED => 'Suspended',
            self::REMOVED => 'Removed',
        };
    }

    /**
     * Only an active membership may establish TenantContext.
     * See Keryon_Identity_Membership_Authorization_Architecture_v1.4.1.md §6.
     */
    public function grantsAccess(): bool
    {
        return $this === self::ACTIVE;
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->all();
    }
}
