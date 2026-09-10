<?php

namespace App\Filament\Clusters\Website\Resources;

use App\Enums\PublicationType;
use App\Filament\Clusters\Website;
use App\Filament\Clusters\Website\Resources\ChurchPublicationResource\Pages\ListChurchPublications;
use App\Filament\Clusters\Website\WebsiteNavigation;
use App\Filament\Support\MediaSelectField;
use App\Models\ChurchPublication;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
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
 * K-WEB-V1-001D-C §32-41/§51 — the pastor/Church-authored books/
 * resources catalogue. `price_text` is bounded display metadata, never
 * a transactional field (§37); `purchase_url` is an optional external
 * destination only — Keryon builds no checkout (§34/§40).
 */
class ChurchPublicationResource extends Resource
{
    protected static ?string $model = ChurchPublication::class;

    protected static ?string $cluster = Website::class;

    protected static ?string $navigationLabel = 'Publications';

    protected static string|\UnitEnum|null $navigationGroup = WebsiteNavigation::CONTENT;

    protected static ?int $navigationSort = 7;

    protected static ?string $modelLabel = 'publication';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make()
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('author')
                            ->maxLength(255)
                            ->helperText('The pastor, ministry, or writer — not necessarily your Lead Pastor.'),
                        Select::make('publication_type')
                            ->label('Type')
                            ->options(PublicationType::options())
                            ->native(false)
                            ->required()
                            ->default(PublicationType::Book->value),
                        Textarea::make('description')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                        MediaSelectField::make('cover_id', 'Cover'),
                        TextInput::make('cover_alt_override')
                            ->label('Cover alt text (optional override)')
                            ->helperText("Leave blank to use the image's own default description.")
                            ->maxLength(255),
                        TextInput::make('price_text')
                            ->label('Price (optional)')
                            ->placeholder('e.g. ₦8,000, $19.99, or Free')
                            ->maxLength(60)
                            ->helperText('Shown as plain text — Keryon does not process payments.'),
                        TextInput::make('purchase_url')
                            ->label('Where to buy / order (optional)')
                            ->url()
                            ->maxLength(500),
                        Toggle::make('is_featured')
                            ->label('Feature this publication'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('cover.path')
                    ->label('')
                    ->disk(fn (ChurchPublication $record): ?string => $record->cover?->disk)
                    ->square(),
                TextColumn::make('title')
                    ->weight('semibold')
                    ->searchable(),
                TextColumn::make('author')
                    ->placeholder('—')
                    ->visibleFrom('md'),
                TextColumn::make('publication_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (PublicationType $state): string => $state->label()),
                TextColumn::make('price_text')
                    ->label('Price')
                    ->placeholder('—')
                    ->visibleFrom('lg'),
                IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean(),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->actions([
                ActionGroup::make([
                    EditAction::make()->slideOver()->modalWidth(Width::TwoExtraLarge),
                    DeleteAction::make(),
                ]),
            ])
            ->emptyStateHeading('No publications yet')
            ->emptyStateDescription('Add a book or resource for your church website to show visitors.')
            ->emptyStateIcon('heroicon-o-book-open')
            ->emptyStateActions([
                static::createAction(),
            ]);
    }

    public static function createAction(): CreateAction
    {
        return CreateAction::make()
            ->label('Add publication')
            ->slideOver()
            ->modalWidth(Width::TwoExtraLarge)
            ->createAnother(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChurchPublications::route('/'),
        ];
    }
}
