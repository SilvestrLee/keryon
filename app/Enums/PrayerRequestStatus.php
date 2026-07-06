<?php

namespace App\Enums;

enum PrayerRequestStatus: string
{
    case NEW = 'new';
    case REVIEWED = 'reviewed';
    case PRAYING = 'praying';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::NEW => 'New',
            self::REVIEWED => 'Reviewed',
            self::PRAYING => 'Praying',
            self::CLOSED => 'Closed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NEW => 'warning',
            self::REVIEWED => 'info',
            self::PRAYING => 'success',
            self::CLOSED => 'gray',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->all();
    }
}
