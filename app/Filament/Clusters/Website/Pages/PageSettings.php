<?php

namespace App\Filament\Clusters\Website\Pages;

use App\Enums\WebsitePageType;
use App\Filament\Clusters\Website;
use App\Filament\Clusters\Website\WebsiteNavigation;
use App\Models\WebsitePageSetting;
use App\Support\TenantContext;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;

/**
 * K-WEB-V1-001D-B §44/§64 — the smallest coherent UX needed to prove the
 * canonical page registry actually works end to end: a bounded page
 * listing every *optional* page type this milestone ships (About,
 * Leadership, Ministries — Home/Contact are required and never shown
 * here, per §32), each with exactly three controls (enabled, navigation
 * order, navigation-label override). Deliberately NOT a generic
 * page/menu builder — no add/remove row, no arbitrary link, no HTML/
 * Markdown field. See §38 for the navigation-label validation rules this
 * form enforces (plain text, bounded length, never the canonical
 * identity).
 */
class PageSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $cluster = Website::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationLabel = 'Pages';

    protected static string|\UnitEnum|null $navigationGroup = WebsiteNavigation::CONFIGURATION;

    protected static ?string $title = 'Page settings';

    protected static ?int $navigationSort = 9;

    protected string $view = 'filament.clusters.website.pages.page-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', WebsitePageSetting::class);
    }

    /** @return list<WebsitePageType> */
    public static function optionalPageTypes(): array
    {
        return array_values(array_filter(WebsitePageType::cases(), fn (WebsitePageType $type): bool => ! $type->required()));
    }

    public function mount(): void
    {
        // `WebsitePageSetting::query()` is already tenant-scoped via
        // `BelongsToChurch` — no manual church filtering needed here.
        $existing = WebsitePageSetting::query()->get()->keyBy(fn (WebsitePageSetting $setting): string => $setting->page_type->value);

        $state = [];
        foreach (self::optionalPageTypes() as $type) {
            $row = $existing->get($type->value);
            $state[$type->value] = [
                'enabled' => $row->enabled ?? $type->defaultEnabledWhenUnconfigured(),
                'nav_order' => $row->nav_order ?? $type->defaultNavOrder(),
                'navigation_label' => $row->navigation_label ?? null,
            ];
        }

        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(array_map(fn (WebsitePageType $type): Section => Section::make($type->label())
                ->description($type->description())
                ->schema([
                    Toggle::make("{$type->value}.enabled")
                        ->label('Show on your website'),
                    TextInput::make("{$type->value}.nav_order")
                        ->label('Navigation order')
                        ->numeric()
                        ->minValue(1)
                        ->helperText('Lower numbers appear first in your navigation menu.'),
                    TextInput::make("{$type->value}.navigation_label")
                        ->label('Navigation label')
                        ->maxLength(60)
                        ->placeholder($type->label())
                        ->helperText("Shown in your website menu instead of \"{$type->label()}\". Leave blank to use the default."),
                ])
                ->columns(3), self::optionalPageTypes()))
            ->statePath('data');
    }

    public function save(): void
    {
        Gate::authorize('create', WebsitePageSetting::class);

        $churchId = app(TenantContext::class)->currentChurchId();
        $state = $this->form->getState();

        foreach (self::optionalPageTypes() as $type) {
            $row = $state[$type->value] ?? [];

            WebsitePageSetting::query()->updateOrCreate(
                ['church_id' => $churchId, 'page_type' => $type->value],
                [
                    'enabled' => (bool) ($row['enabled'] ?? $type->defaultEnabledWhenUnconfigured()),
                    'nav_order' => $row['nav_order'] !== null && $row['nav_order'] !== '' ? (int) $row['nav_order'] : $type->defaultNavOrder(),
                    'navigation_label' => filled($row['navigation_label'] ?? null) ? $row['navigation_label'] : null,
                ],
            );
        }

        Notification::make()
            ->title('Saved')
            ->success()
            ->send();
    }
}
