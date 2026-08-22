<?php

namespace App\PublicWebsite\Themes;

use App\Models\Church;
use App\Models\WebsitePublication;
use Illuminate\Contracts\View\View;

interface ThemeRenderer
{
    public function renderWorking(string $page, Church $church, bool $preview = false): View;

    public function renderPublished(string $page, WebsitePublication $publication): View;
}
