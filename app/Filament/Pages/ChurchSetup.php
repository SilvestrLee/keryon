<?php

namespace App\Filament\Pages;

use App\Support\TenantContext;
use Filament\Pages\Page;

class ChurchSetup extends Page
{
    protected string $view = 'filament.pages.church-setup';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'setup';

    protected static ?string $title = 'Church Workspace Access';

    public function mount(): void
    {
        if (app(TenantContext::class)->hasContext()) {
            $this->redirect(filament()->getHomeUrl());
        }
    }

    public function save(): void
    {
        abort(403, 'Church provisioning is assisted by Keryon. Contact support for workspace access.');
    }
}
