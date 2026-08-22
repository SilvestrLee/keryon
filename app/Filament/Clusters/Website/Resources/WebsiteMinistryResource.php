<?php

namespace App\Filament\Clusters\Website\Resources;

use App\Filament\Clusters\Website;
use App\Filament\Clusters\Website\Resources\WebsiteMinistryResource\Pages\ListWebsiteMinistries;
use App\Filament\Support\MediaSelectField;
use App\Models\WebsiteMinistry;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * K-CHURCHWEB-001C §19 — curated Ministries management. Public website
 * presentation content only — not group/volunteer/events management.
 */
class WebsiteMinistryResource extends Resource
{
    protected static ?string $model = WebsiteMinistry::class;

    protected static ?string $cluster = Website::class;

    protected static ?string $navigationLabel = 'Ministries';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'ministry';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-heart';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make()
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        MediaSelectField::make('image_id', 'Image'),
                        TextInput::make('image_alt_override')
                            ->label('Image alt text (optional override)')
                            ->helperText("Leave blank to use the image's own default description.")
                            ->maxLength(255),
                        Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image.path')
                    ->label('')
                    ->disk(fn (WebsiteMinistry $record): ?string => $record->image?->disk)
                    ->square(),
                TextColumn::make('name')
                    ->weight('semibold')
                    ->searchable(),
                TextColumn::make('description')
                    ->limit(80)
                    ->color('gray')
                    ->visibleFrom('md'),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->actions([
                ActionGroup::make([
                    EditAction::make()->slideOver()->modalWidth(Width::TwoExtraLarge),
                    DeleteAction::make(),
                ]),
            ])
            ->emptyStateHeading('No ministries yet')
            ->emptyStateDescription('Ministries help visitors understand the life and work of your church.')
            ->emptyStateIcon('heroicon-o-heart')
            ->emptyStateActions([
                static::createAction(),
            ]);
    }

    public static function createAction(): CreateAction
    {
        return CreateAction::make()
            ->slideOver()
            ->modalWidth(Width::TwoExtraLarge)
            ->createAnother(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWebsiteMinistries::route('/'),
        ];
    }
}
