<?php

namespace App\Enums;

/**
 * K-CHURCHWEB-001B §24 — a bounded catalogue of platforms a church may
 * link to. Deliberately just a public URL/handle record, never an OAuth
 * connection or publishing-account integration — those are separate,
 * later concepts (native social publishing) this enum must not be
 * confused with.
 */
enum SocialPlatform: string
{
    case FACEBOOK = 'facebook';
    case INSTAGRAM = 'instagram';
    case YOUTUBE = 'youtube';
    case TIKTOK = 'tiktok';
    case X = 'x';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::FACEBOOK => 'Facebook',
            self::INSTAGRAM => 'Instagram',
            self::YOUTUBE => 'YouTube',
            self::TIKTOK => 'TikTok',
            self::X => 'X',
            self::OTHER => 'Other',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->all();
    }
}
