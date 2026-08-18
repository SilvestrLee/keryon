<?php

namespace App\Enums;

/**
 * System-defined church responsibility roles. Composable — a single
 * ChurchMembership may hold more than one. Churches cannot define their
 * own roles. See Keryon Blueprint v1.4.1 §8-§9.
 *
 * Deliberately excludes Primary Administrator — that is an account-ownership
 * designation (ChurchMembership::is_primary), not a responsibility role.
 * See Keryon Blueprint v1.4.1 §7.
 */
enum ChurchRole: string
{
    case ADMINISTRATOR = 'administrator';
    case COMMUNICATIONS = 'communications';
    case CARE = 'care';

    public function label(): string
    {
        return match ($this) {
            self::ADMINISTRATOR => 'Administrator',
            self::COMMUNICATIONS => 'Communications',
            self::CARE => 'Care',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->all();
    }
}
