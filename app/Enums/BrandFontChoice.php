<?php

namespace App\Enums;

/**
 * K-CHURCHWEB-001B §9 — Typography Safety. A church's Brand Profile may
 * express a heading/body typography *preference*, but never an arbitrary
 * uploaded font file. This is the fixed, curated catalogue that preference
 * is bounded to — the same "controlled theme-supported choices" model the
 * directive requires, not a large speculative type foundry. Themes decide
 * how (or whether) to honor a given choice; this enum only records intent.
 */
enum BrandFontChoice: string
{
    case INTER = 'inter';
    case GEIST = 'geist';
    case PLAYFAIR_DISPLAY = 'playfair_display';
    case MERRIWEATHER = 'merriweather';
    case SOURCE_SERIF = 'source_serif';

    public function label(): string
    {
        return match ($this) {
            self::INTER => 'Inter',
            self::GEIST => 'Geist',
            self::PLAYFAIR_DISPLAY => 'Playfair Display',
            self::MERRIWEATHER => 'Merriweather',
            self::SOURCE_SERIF => 'Source Serif',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->all();
    }
}
