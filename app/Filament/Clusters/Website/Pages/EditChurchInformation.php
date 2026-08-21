<?php

namespace App\Filament\Clusters\Website\Pages;

use App\Enums\Capability;
use App\Enums\DayOfWeek;
use App\Enums\SocialPlatform;
use App\Filament\Clusters\Website;
use App\Models\Church;
use App\Support\TenantContext;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;

/**
 * K-CHURCHWEB-001C §21-§24 — the institutional information Website
 * consumes but does not own: Church name/email/phone/address, Service
 * Times, and Social Links. Deliberately not called "Website Settings" —
 * this data belongs to the Church and is shared across Keryon, per
 * K-CHURCHWEB-001B §5/§22-§24.
 */
class EditChurchInformation extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $cluster = Website::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationLabel = 'Church Information';

    protected static ?string $title = 'Church Information';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.clusters.website.pages.edit-church-information';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return app(TenantContext::class)->currentMembership()?->hasCapability(Capability::ChurchIdentityView) ?? false;
    }

    /**
     * Resolved fresh via TenantContext on every call rather than cached
     * on a property — Livewire only rehydrates *public* properties across
     * the request boundary, and TenantContext itself already resolves
     * cheaply and correctly per-request regardless.
     */
    protected function church(): Church
    {
        return app(TenantContext::class)->currentChurch();
    }

    public function mount(): void
    {
        Gate::authorize('view', $this->church());

        $this->form->fill($this->church()->only(['name', 'email', 'phone', 'address']));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->model($this->church())
            ->components([
                Section::make('Church Information')
                    ->description('Shared across Keryon — used on your website and other church communications.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Church name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('Phone')
                            ->tel()
                            ->maxLength(255),
                        Textarea::make('address')
                            ->label('Address')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Service Times')
                    ->description('The regular services your church holds.')
                    ->schema([
                        Repeater::make('serviceTimes')
                            ->relationship('serviceTimes')
                            ->label('')
                            ->schema([
                                TextInput::make('label')
                                    ->label('Name')
                                    ->placeholder('e.g. Sunday Worship')
                                    ->required()
                                    ->maxLength(255),
                                Select::make('day_of_week')
                                    ->label('Day')
                                    ->options(DayOfWeek::options())
                                    ->native(false),
                                TextInput::make('time')
                                    ->label('Time')
                                    ->placeholder('e.g. 10:00 AM')
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->columns(3)
                            ->orderColumn('sort_order')
                            ->reorderable()
                            ->addActionLabel('Add a service time')
                            ->defaultItems(0),
                    ]),
                Section::make('Social Links')
                    ->description('Where visitors can find your church online.')
                    ->schema([
                        Repeater::make('socialLinks')
                            ->relationship('socialLinks')
                            ->label('')
                            ->schema([
                                Select::make('platform')
                                    ->label('Platform')
                                    ->options(SocialPlatform::options())
                                    ->native(false)
                                    ->required(),
                                TextInput::make('url')
                                    ->label('Link')
                                    ->url()
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->columns(2)
                            ->orderColumn('sort_order')
                            ->reorderable()
                            ->addActionLabel('Add a social link')
                            ->defaultItems(0),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $church = $this->church();

        Gate::authorize('update', $church);

        $state = $this->form->getState();

        $church->update([
            'name' => $state['name'],
            'email' => $state['email'] ?: null,
            'phone' => $state['phone'] ?: null,
            'address' => $state['address'] ?: null,
        ]);

        Notification::make()
            ->title('Saved')
            ->success()
            ->send();
    }
}
