<?php

namespace App\Filament\Pages;

use App\Models\Church;
use Filament\Pages\Page;
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
        if (auth()->user()->church_id) {
            $this->redirect(filament()->getHomeUrl());
        }
    }

    public function save(): void
    {
        $this->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'nullable|email|max:255',
            'timezone' => 'required|timezone',
        ]);

        $church = Church::create([
            'name'      => $this->name,
            'slug'      => Str::slug($this->name) . '-' . now()->timestamp,
            'email'     => $this->email ?: null,
            'timezone'  => $this->timezone,
            'is_active' => true,
        ]);

        auth()->user()->update(['church_id' => $church->id]);

        $this->redirect(filament()->getHomeUrl());
    }
}
