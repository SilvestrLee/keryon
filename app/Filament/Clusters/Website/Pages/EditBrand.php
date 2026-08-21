<?php

namespace App\Filament\Clusters\Website\Pages;

use App\Filament\Clusters\Website;
use App\Filament\Clusters\Website\Concerns\ManagesSingletonRecord;
use App\Filament\Support\MediaSelectField;
use App\Models\ChurchBrandProfile;
use App\Enums\BrandFontChoice;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;

/**
 * K-CHURCHWEB-001C §24 — the shared Church Brand Profile. Explicitly does
 * not mention Design Studio (it does not exist yet) — see §24's own
 * instruction. Only the fields K-CHURCHWEB-001B actually persisted.
 */
class EditBrand extends Page implements HasForms
{
    use ManagesSingletonRecord;

    protected static ?string $cluster = Website::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-swatch';

    protected static ?string $navigationLabel = 'Brand';

    protected static ?string $title = 'Brand';

    protected static ?int $navigationSort = 7;

    protected string $view = 'filament.clusters.website.pages.edit-brand';

    public static function modelClass(): string
    {
        return ChurchBrandProfile::class;
    }

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', ChurchBrandProfile::class);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Text::make('Your church brand is shared across Keryon experiences that represent your church — starting with your website.')
                    ->color('gray'),
                Section::make('Logo')
                    ->schema([
                        MediaSelectField::make('primary_logo_media_id', 'Primary logo'),
                        MediaSelectField::make('mark_media_id', 'Mark (square icon)'),
                    ])
                    ->columns(2),
                Section::make('Colors')
                    ->schema([
                        ColorPicker::make('primary_color')->label('Primary color'),
                        ColorPicker::make('secondary_color')->label('Secondary color'),
                        ColorPicker::make('accent_color')->label('Accent color'),
                    ])
                    ->columns(3),
                Section::make('Typography')
                    ->schema([
                        Select::make('heading_font')
                            ->label('Heading typeface')
                            ->options(BrandFontChoice::options())
                            ->native(false),
                        Select::make('body_font')
                            ->label('Body typeface')
                            ->options(BrandFontChoice::options())
                            ->native(false),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }
}
