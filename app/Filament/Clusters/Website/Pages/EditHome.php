<?php

namespace App\Filament\Clusters\Website\Pages;

use App\Filament\Clusters\Website;
use App\Filament\Clusters\Website\Concerns\ManagesSingletonRecord;
use App\Filament\Support\MediaSelectField;
use App\Models\WebsiteHomeContent;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;

/**
 * K-CHURCHWEB-001C §16 — Home management. Only the fields K-CHURCHWEB-001B
 * actually persisted (Hero, Welcome, Scripture Highlight) — "Featured
 * Ministries"/"Featured Campaign"/"Latest News" were deliberately not
 * built (they depend on Campaigns/Content Studio handoff, both out of
 * scope) and so have no field here either.
 */
class EditHome extends Page implements HasForms
{
    use ManagesSingletonRecord;

    protected static ?string $cluster = Website::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Home';

    protected static ?string $title = 'Home';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.clusters.website.pages.edit-home';

    public static function modelClass(): string
    {
        return WebsiteHomeContent::class;
    }

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', WebsiteHomeContent::class);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Hero')
                    ->description('The first thing a visitor sees on your homepage.')
                    ->schema([
                        TextInput::make('hero_heading')
                            ->label('Heading')
                            ->maxLength(255),
                        Textarea::make('hero_subheading')
                            ->label('Supporting text')
                            ->rows(3),
                        MediaSelectField::make('hero_image_id', 'Hero image'),
                        TextInput::make('hero_image_alt_override')
                            ->label('Hero image alt text (optional override)')
                            ->helperText("Leave blank to use the image's own default description.")
                            ->maxLength(255),
                        TextInput::make('hero_cta_label')
                            ->label('Button label')
                            ->maxLength(255),
                        TextInput::make('hero_cta_url')
                            ->label('Button link')
                            ->url()
                            ->maxLength(255),
                    ])
                    ->columns(2),
                Section::make('Welcome')
                    ->description('A short welcome for first-time visitors.')
                    ->schema([
                        TextInput::make('welcome_heading')
                            ->label('Heading')
                            ->maxLength(255),
                        Textarea::make('welcome_body')
                            ->label('Message')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Scripture Highlight')
                    ->description('A verse your church wants visitors to see.')
                    ->schema([
                        TextInput::make('scripture_reference')
                            ->label('Reference')
                            ->placeholder('e.g. Romans 5:3-5')
                            ->maxLength(255),
                        Textarea::make('scripture_text')
                            ->label('Text')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }
}
