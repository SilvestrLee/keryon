<?php

namespace App\Enums;

/**
 * K-CHURCHWEB-001B §19 — the grouping Blueprint v1.3 Module 3 §1 lists
 * under "Leadership" (Pastor, Ministers, Elders, Team Profiles). The
 * specific title (e.g. "Senior Pastor", "Worship Elder") stays free text
 * on WebsiteLeadershipProfile::role_title — real church titles vary too
 * much for a rigid enum — this only records which broad group a profile
 * belongs to, for grouping/ordering on the rendered page.
 */
enum LeadershipCategory: string
{
    case PASTOR = 'pastor';
    case MINISTER = 'minister';
    case ELDER = 'elder';
    case TEAM = 'team';

    public function label(): string
    {
        return match ($this) {
            self::PASTOR => 'Pastor',
            self::MINISTER => 'Minister',
            self::ELDER => 'Elder',
            self::TEAM => 'Team',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->all();
    }
}
