<?php

namespace App\Enums;

// System-controlled — never a user-selectable form field. See
// K-CONTENT-002 §11. FaithFlow itself is not implemented by this
// migration; this case exists only so future FaithFlow-originated
// content doesn't require a schema change to be identified.
enum ContentOrigin: string
{
    case HUMAN = 'human';
    case FAITHFLOW = 'faithflow';

    public function label(): string
    {
        return match ($this) {
            self::HUMAN => 'Human',
            self::FAITHFLOW => 'FaithFlow',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->all();
    }
}
