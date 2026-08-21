<?php

namespace App\Enums;

/**
 * K-CHURCHWEB-001B §25 — Theme Catalogue. Deliberately a code-registered
 * enum, not a database table: themes are Keryon-curated implementations,
 * never user-uploadable, and a persisted `themes` table would store
 * nothing a fixed enum doesn't already express more simply (no per-theme
 * runtime configuration exists yet — see K-CHURCHWEB-001A §21/§25). If a
 * future theme genuinely needs its own configurable metadata, that is the
 * moment to reconsider a table; do not add one speculatively now.
 *
 * Proclaim is the only approved theme (K-CHURCHWEB-001A §16) — this enum
 * exists so `WebsiteSettings::theme` has somewhere bounded to point at,
 * not to imply a marketplace of interchangeable themes exists today.
 * Selecting a theme never touches Website Content rows — see
 * `WebsiteSettings` and the K-CHURCHWEB-001B report §13/§14.
 */
enum WebsiteTheme: string
{
    case PROCLAIM = 'proclaim';

    public function label(): string
    {
        return match ($this) {
            self::PROCLAIM => 'Proclaim',
        };
    }
}
