<?php

namespace App\Enums;

/**
 * K-WEB-V1-001D-C §36 — a bounded v1 value set for `ChurchPublication`,
 * following the exact precedent `LeadershipCategory` already establishes
 * for a similar "grouping, not a type-builder" need. Not
 * Administrator-configurable — a fixed, Keryon-curated set.
 */
enum PublicationType: string
{
    case Book = 'book';
    case Devotional = 'devotional';
    case StudyGuide = 'study_guide';
    case Workbook = 'workbook';
    case Resource = 'resource';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Book => 'Book',
            self::Devotional => 'Devotional',
            self::StudyGuide => 'Study Guide',
            self::Workbook => 'Workbook',
            self::Resource => 'Resource',
            self::Other => 'Other',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
