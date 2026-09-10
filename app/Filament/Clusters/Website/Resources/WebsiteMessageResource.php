<?php

namespace App\Filament\Clusters\Website\Resources;

use App\Filament\Clusters\Website;
use App\Filament\Clusters\Website\Resources\WebsiteMessageResource\Pages\ListWebsiteMessages;
use App\Filament\Clusters\Website\WebsiteNavigation;
use App\Filament\Support\MediaSelectField;
use App\Models\WebsiteMessage;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
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
 * K-WEB-V1-001D-C §25-31/§51 — a lightweight public sermon/message
 * catalogue. `media_url` always points to an external destination
 * (§28) — no upload field, no MIME expansion, exists here.
 */
class WebsiteMessageResource extends Resource
{
    protected static ?string $model = WebsiteMessage::class;

    protected static ?string $cluster = Website::class;

    protected static ?string $navigationLabel = 'Messages';

    protected static string|\UnitEnum|null $navigationGroup = WebsiteNavigation::CONTENT;

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'message';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-play-circle';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make()
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('speaker')
                            ->maxLength(255)
                            ->helperText('Plain text — the person who delivered this message.'),
                        DatePicker::make('message_date')
                            ->label('Date')
                            ->native(false),
                        TextInput::make('scripture_reference')
                            ->label('Scripture reference (optional)')
                            ->placeholder('e.g. Romans 15:7')
                            ->maxLength(255),
                        Textarea::make('summary')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                        MediaSelectField::make('image_id', 'Image'),
                        TextInput::make('image_alt_override')
                            ->label('Image alt text (optional override)')
                            ->helperText("Leave blank to use the image's own default description.")
                            ->maxLength(255),
                        TextInput::make('media_url')
                            ->label('Watch or listen link')
                            ->url()
                            ->maxLength(500)
                            ->helperText('A YouTube, Vimeo, or audio/podcast link where visitors can watch or listen. Keryon does not host video or audio.'),
                        Toggle::make('is_featured')
                            ->label('Feature this message'),
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
                    ->disk(fn (WebsiteMessage $record): ?string => $record->image?->disk)
                    ->square(),
                TextColumn::make('title')
                    ->weight('semibold')
                    ->searchable(),
                TextColumn::make('speaker')
                    ->placeholder('—')
                    ->visibleFrom('md'),
                TextColumn::make('message_date')
                    ->label('Date')
                    ->date('M j, Y')
                    ->sortable()
                    ->placeholder('—'),
                IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean(),
            ])
            ->defaultSort('message_date', 'desc')
            ->actions([
                ActionGroup::make([
                    EditAction::make()->slideOver()->modalWidth(Width::TwoExtraLarge),
                    DeleteAction::make(),
                ]),
            ])
            ->emptyStateHeading('No messages yet')
            ->emptyStateDescription('Add a message for your church website to show visitors.')
            ->emptyStateIcon('heroicon-o-play-circle')
            ->emptyStateActions([
                static::createAction(),
            ]);
    }

    public static function createAction(): CreateAction
    {
        return CreateAction::make()
            ->label('Add message')
            ->slideOver()
            ->modalWidth(Width::TwoExtraLarge)
            ->createAnother(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWebsiteMessages::route('/'),
        ];
    }
}
