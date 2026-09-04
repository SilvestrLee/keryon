<?php

namespace App\Enums;

// System-controlled — never a user-selectable form field. See
// K-CONTENT-002 §11. FaithFlow itself is not implemented by this
// migration; this case exists only so future FaithFlow-originated
// content doesn't require a schema change to be identified.
// K-ORG-COMMS-001E §12 — `ORGANIZATION_IMPORT` marks a ContentItem
// explicitly created through a Church's own deliberate Organization
// Communication import. It is distinct from `FAITHFLOW` (a different,
// unrelated origin) and from `HUMAN` — this is still Church-owned content
// from the moment it exists, but its starting body came from an
// Organization source, not free typing. Never user-selectable.
enum ContentOrigin: string
{
    case HUMAN = 'human';
    case FAITHFLOW = 'faithflow';
    case ORGANIZATION_IMPORT = 'organization_import';

    public function label(): string
    {
        return match ($this) {
            self::HUMAN => 'Human',
            self::FAITHFLOW => 'FaithFlow',
            self::ORGANIZATION_IMPORT => 'Organization import',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->all();
    }
}
