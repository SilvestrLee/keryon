<?php

namespace App\Filament\Clusters\Website\Pages;

use App\Filament\Clusters\Website;
use App\Filament\Clusters\Website\Concerns\ManagesSingletonRecord;
use App\Models\WebsiteAboutContent;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;

/**
 * K-CHURCHWEB-001C §17 — About management, deliberately separated into
 * Church Story / Vision / Mission / Leadership Introduction rather than
 * one undifferentiated form, so the resulting public page structure
 * stays legible.
 */
class EditAbout extends Page implements HasForms
{
    use ManagesSingletonRecord;

    protected static ?string $cluster = Website::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'About';

    protected static ?string $title = 'About';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.clusters.website.pages.edit-about';

    public static function modelClass(): string
    {
        return WebsiteAboutContent::class;
    }

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', WebsiteAboutContent::class);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Church Story')
                    ->description('How your church came to be, and where it is today.')
                    ->schema([
                        Textarea::make('church_story')->label('')->rows(5),
                    ]),
                Section::make('Vision')
                    ->description('Where your church is heading.')
                    ->schema([
                        Textarea::make('vision')->label('')->rows(4),
                    ]),
                Section::make('Mission')
                    ->description('What your church exists to do.')
                    ->schema([
                        Textarea::make('mission')->label('')->rows(4),
                    ]),
                Section::make('Leadership Introduction')
                    ->description('A short introduction before your Leadership page.')
                    ->schema([
                        Textarea::make('leadership_introduction')->label('')->rows(3),
                    ]),
            ])
            ->statePath('data');
    }
}
