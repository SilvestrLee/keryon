<?php

namespace App\PublicWebsite\Themes;

use App\Enums\WebsiteTheme;
use App\PublicWebsite\Themes\Proclaim\ProclaimTheme;

class ThemeRegistry
{
    /** @var array<string, class-string<ThemeRenderer>> */
    private array $themes = [
        WebsiteTheme::PROCLAIM->value => ProclaimTheme::class,
    ];

    public function resolve(string $theme): ?ThemeRenderer
    {
        $renderer = $this->themes[$theme] ?? null;

        return $renderer ? app($renderer) : null;
    }
}
