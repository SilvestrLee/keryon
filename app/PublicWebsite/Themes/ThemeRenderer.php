<?php

namespace App\PublicWebsite\Themes;

use App\Enums\WebsitePageType;
use App\Models\Church;
use App\Models\WebsitePublication;
use Illuminate\Contracts\View\View;

interface ThemeRenderer
{
    public function renderWorking(string $page, Church $church, bool $preview = false): View;

    public function renderPublished(string $page, WebsitePublication $publication): View;

    /**
     * K-WEB-V1-001D-B §16/§17 — the canonical page types this theme
     * knows how to render. Page *identity* belongs to the platform
     * (`WebsitePageType`); this method is the only place a theme
     * declares which of those already-defined identities it supports —
     * it never invents a new one. Callers (route/preview dispatch,
     * navigation resolution) must treat any type absent from this list
     * as unsupported and fail closed, never attempt a fallback render.
     *
     * @return list<WebsitePageType>
     */
    public function supportedPageTypes(): array;
}
