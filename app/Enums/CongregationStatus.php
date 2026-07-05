<?php

namespace App\Enums;

enum CongregationStatus: string
{
    case ACTIVE = 'active';
    case VISITOR = 'visitor';
    case INACTIVE = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::VISITOR => 'Visitor',
            self::INACTIVE => 'Inactive',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::VISITOR => 'warning',
            self::INACTIVE => 'gray',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->all();
    }
}
