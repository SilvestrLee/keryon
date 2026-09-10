<?php

namespace App\Filament\Clusters\Website\Resources;

use App\Filament\Clusters\Website;
use App\Filament\Clusters\Website\Resources\WebsiteEventResource\Pages\ListWebsiteEvents;
use App\Filament\Clusters\Website\WebsiteNavigation;
use App\Filament\Support\MediaSelectField;
use App\Models\WebsiteEvent;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * K-WEB-V1-001D-C §17-24/§51 — curated Events management, following the
 * exact `WebsiteLeadershipResource`/`WebsiteMinistryResource` pattern.
 * Informational Website publishing only — no registration, RSVP,
 * ticketing, attendance, capacity, or check-in field exists anywhere on
 * this form (§17).
 */
class WebsiteEventResource extends Resource
{
    protected static ?string $model = WebsiteEvent::class;

    protected static ?string $cluster = Website::class;

    protected static ?string $navigationLabel = 'Events';

    protected static string|\UnitEnum|null $navigationGroup = WebsiteNavigation::CONTENT;

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'event';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make()
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('summary')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                        DateTimePicker::make('starts_at')
                            ->label('Starts')
                            ->required()
                            ->native(false),
                        DateTimePicker::make('ends_at')
                            ->label('Ends (optional)')
                            ->native(false)
                            ->after('starts_at'),
                        TextInput::make('venue')
                            ->maxLength(255),
                        MediaSelectField::make('image_id', 'Image'),
                        TextInput::make('image_alt_override')
                            ->label('Image alt text (optional override)')
                            ->helperText("Leave blank to use the image's own default description.")
                            ->maxLength(255),
                        TextInput::make('cta_label')
                            ->label('Button label (optional)')
                            ->maxLength(60),
                        TextInput::make('cta_url')
                            ->label('Button link (optional)')
                            ->url()
                            ->maxLength(500)
                            ->helperText('Where the button should take visitors — e.g. a registration page you manage elsewhere.'),
                        Toggle::make('is_featured')
                            ->label('Feature this event'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image.path')
                    ->label('')
                    ->disk(fn (WebsiteEvent $record): ?string => $record->image?->disk)
                    ->square(),
                TextColumn::make('title')
                    ->weight('semibold')
                    ->searchable(),
                TextColumn::make('starts_at')
                    ->label('Starts')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),
                TextColumn::make('venue')
                    ->placeholder('—')
                    ->visibleFrom('md'),
                IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean(),
            ])
            ->defaultSort('starts_at')
            ->reorderable('sort_order')
            ->actions([
                ActionGroup::make([
                    EditAction::make()->slideOver()->modalWidth(Width::TwoExtraLarge),
                    DeleteAction::make(),
                ]),
            ])
            ->emptyStateHeading('No events yet')
            ->emptyStateDescription('Add an upcoming event for your church website to show visitors.')
            ->emptyStateIcon('heroicon-o-calendar-days')
            ->emptyStateActions([
                static::createAction(),
            ]);
    }

    public static function createAction(): CreateAction
    {
        return CreateAction::make()
            ->label('Add event')
            ->slideOver()
            ->modalWidth(Width::TwoExtraLarge)
            ->createAnother(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWebsiteEvents::route('/'),
        ];
    }
}
