<?php

namespace App\Filament\Clusters\Website\Pages;

use App\Enums\WebsiteTheme;
use App\Filament\Clusters\Website;
use App\Models\WebsiteSettings;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;

/**
 * K-CHURCHWEB-001C §5/§28/§29 — Theme management. Product Office
 * correction implemented literally: changing `theme` is authorized
 * against `changeTheme` (→ `WebsiteThemeManage`), separately from
 * `footer_note`, which stays governed by the ordinary `update` ability
 * (→ `WebsiteContentManage`) — both fields live on one WebsiteSettings
 * row, but the two *actions* are authorized independently. See §22/§23
 * for the regression evidence this enables.
 *
 * Only Proclaim is a registered theme (§28) — no fabricated alternative
 * choices. The Radio option list is built from `WebsiteTheme::cases()`,
 * so a future second theme needs no UI change here.
 */
class EditTheme extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $cluster = Website::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-paint-brush';

    protected static ?string $navigationLabel = 'Theme';

    protected static ?string $title = 'Theme';

    protected static ?int $navigationSort = 8;

    protected string $view = 'filament.clusters.website.pages.edit-theme';

    public ?array $data = [];

    protected ?WebsiteSettings $record = null;

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', WebsiteSettings::class);
    }

    public function mount(): void
    {
        $this->record = WebsiteSettings::query()->first();

        $this->form->fill([
            'theme' => $this->record?->theme?->value ?? WebsiteTheme::PROCLAIM->value,
            'footer_note' => $this->record?->footer_note,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Website Theme')
                    ->description(count(WebsiteTheme::cases()) === 1
                        ? 'Proclaim is currently the only Keryon website theme available. More will be added over time — changing theme will never rewrite your website content.'
                        : 'Choose the professional design your website uses. Changing theme never rewrites your website content.')
                    ->schema([
                        Radio::make('theme')
                            ->label('')
                            ->options(collect(WebsiteTheme::cases())->mapWithKeys(fn (WebsiteTheme $theme) => [$theme->value => $theme->label()]))
                            ->default(WebsiteTheme::PROCLAIM->value),
                    ]),
                Section::make('Footer')
                    ->description('A short note shown in your website footer.')
                    ->schema([
                        Textarea::make('footer_note')
                            ->label('')
                            ->rows(2),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        if ($this->record === null) {
            Gate::authorize('create', WebsiteSettings::class);
            $this->record = WebsiteSettings::create([]);
        }

        $themeChanged = $this->record->theme?->value !== $state['theme'];
        $footerChanged = $this->record->footer_note !== $state['footer_note'];

        if ($themeChanged) {
            Gate::authorize('changeTheme', $this->record);
            $this->record->theme = $state['theme'];
        }

        if ($footerChanged) {
            Gate::authorize('update', $this->record);
            $this->record->footer_note = $state['footer_note'];
        }

        $this->record->save();

        Notification::make()
            ->title('Saved')
            ->success()
            ->send();
    }
}
