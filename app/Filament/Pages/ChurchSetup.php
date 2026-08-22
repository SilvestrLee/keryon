<?php

namespace App\Filament\Pages;

use App\Enums\ChurchRole;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Support\TenantContext;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChurchSetup extends Page
{
    protected string $view = 'filament.pages.church-setup';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $slug = 'setup';
    protected static ?string $title = 'Set Up Your Church';

    public string $name = '';
    public string $email = '';
    public string $timezone = 'UTC';

    public function mount(): void
    {
        if (app(TenantContext::class)->hasContext()) {
            $this->redirect(filament()->getHomeUrl());
        }
    }

    /**
     * Creates the Church and its first ChurchMembership — the creator
     * becomes Primary Administrator with the full onboarding-default
     * responsibility bundle (Blueprint v1.4.1 preflight decision #3;
     * §12 — Primary status itself does not imply Care, this bundle is an
     * explicit onboarding default). Also mirrors church_id, the legacy
     * compatibility bridge (Blueprint v1.4.1 §11) that keeps
     * membership-unaware policies working until K-AUTH-001 converts
     * them. Wrapped in a transaction so a self-service Church is never
     * left ownerless if any step fails (K-IDENTITY-001A §33).
     */
    public function save(): void
    {
        $this->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'nullable|email|max:255',
            'timezone' => 'required|timezone',
        ]);

        DB::transaction(function () {
            $church = Church::create([
                'name'      => $this->name,
                'slug'      => Str::slug($this->name) . '-' . now()->timestamp,
                'email'     => $this->email ?: null,
                'timezone'  => $this->timezone,
                'is_active' => true,
            ]);

            $user = auth()->user();

            ChurchMembership::createPrimary($church, $user, [
                ChurchRole::ADMINISTRATOR,
                ChurchRole::COMMUNICATIONS,
                ChurchRole::CARE,
            ]);

            $user->update(['church_id' => $church->id]);
        });

        app(TenantContext::class)->forgetResolved();

        $this->redirect(filament()->getHomeUrl());
    }
}
