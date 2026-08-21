<?php

namespace App\Enums;

/**
 * K-CHURCHWEB-001B §23 — used by ChurchServiceTime for basic day-based
 * sorting/display. Nullable on the model for services that don't map to a
 * single fixed weekday. Not a recurring-schedule/calendar engine — see the
 * directive's explicit "do not build event management" instruction.
 */
enum DayOfWeek: string
{
    case MONDAY = 'monday';
    case TUESDAY = 'tuesday';
    case WEDNESDAY = 'wednesday';
    case THURSDAY = 'thursday';
    case FRIDAY = 'friday';
    case SATURDAY = 'saturday';
    case SUNDAY = 'sunday';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->all();
    }
}
