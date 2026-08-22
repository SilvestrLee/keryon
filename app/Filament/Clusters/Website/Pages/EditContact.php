<?php

namespace App\Filament\Clusters\Website\Pages;

use App\Filament\Clusters\Website;
use App\Filament\Clusters\Website\Concerns\ManagesSingletonRecord;
use App\Models\WebsiteContactContent;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;

/**
 * K-CHURCHWEB-001C §20 — Contact management. Deliberately thin: address,
 * phone and email are institutional Church data, managed on the Church
 * Information page, not duplicated here — see K-CHURCHWEB-001B §5/§22.
 * This page only holds what is genuinely Website-presentation-specific.
 */
class EditContact extends Page implements HasForms
{
    use ManagesSingletonRecord;

    protected static ?string $cluster = Website::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationLabel = 'Contact';

    protected static ?string $title = 'Contact';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.clusters.website.pages.edit-contact';

    public static function modelClass(): string
    {
        return WebsiteContactContent::class;
    }

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', WebsiteContactContent::class);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Contact page')
                    ->description('Your church name, phone, email and address already appear here automatically from Church Information — you only need to add anything below.')
                    ->schema([
                        Textarea::make('office_hours')
                            ->label('Office hours')
                            ->rows(3),
                        TextInput::make('map_embed_url')
                            ->label('Map embed link')
                            ->helperText('A shareable map link for your church address, if you want one shown on the Contact page.')
                            ->url()
                            ->maxLength(255),
                    ]),
            ])
            ->statePath('data');
    }
}
