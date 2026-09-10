<?php

namespace App\Filament\Clusters\Website\Pages;

use App\Filament\Clusters\Website;
use App\Filament\Clusters\Website\Concerns\ManagesSingletonRecord;
use App\Filament\Clusters\Website\WebsiteNavigation;
use App\Filament\Support\MediaSelectField;
use App\Models\WebsiteGivingContent;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;

/**
 * K-WEB-V1-001D-C §42-46/§52 — a singleton Website page editor, exactly
 * like `EditHome`/`EditContact`, not a collection. No transactional
 * field exists anywhere on this form (§46).
 */
class EditGiving extends Page implements HasForms
{
    use ManagesSingletonRecord;

    protected static ?string $cluster = Website::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationLabel = 'Giving';

    protected static string|\UnitEnum|null $navigationGroup = WebsiteNavigation::CONTENT;

    protected static ?string $title = 'Giving';

    protected static ?int $navigationSort = 8;

    protected string $view = 'filament.clusters.website.pages.edit-giving';

    public static function modelClass(): string
    {
        return WebsiteGivingContent::class;
    }

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', WebsiteGivingContent::class);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Giving page')
                    ->description('Explain how visitors can give, and where — Keryon does not process payments.')
                    ->schema([
                        TextInput::make('headline')
                            ->maxLength(255),
                        Textarea::make('body')
                            ->label('Explanation')
                            ->rows(4),
                        MediaSelectField::make('image_id', 'Image (optional)'),
                        TextInput::make('image_alt_override')
                            ->label('Image alt text (optional override)')
                            ->helperText("Leave blank to use the image's own default description.")
                            ->maxLength(255),
                        TextInput::make('cta_label')
                            ->label('Button label (optional)')
                            ->maxLength(60),
                        TextInput::make('giving_url')
                            ->label('Giving link (optional)')
                            ->url()
                            ->maxLength(500)
                            ->helperText('Where the giving button should take visitors — your church\'s own giving or payment page.'),
                        Textarea::make('additional_instructions')
                            ->label('Additional instructions (optional)')
                            ->rows(3)
                            ->helperText('E.g. bank transfer details or other ways to give, in your own words.'),
                    ]),
            ])
            ->statePath('data');
    }
}
