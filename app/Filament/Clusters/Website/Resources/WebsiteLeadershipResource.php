<?php

namespace App\Filament\Clusters\Website\Resources;

use App\Enums\LeadershipCategory;
use App\Filament\Clusters\Website;
use App\Filament\Clusters\Website\Resources\WebsiteLeadershipResource\Pages\ListWebsiteLeadershipProfiles;
use App\Filament\Support\MediaSelectField;
use App\Models\WebsiteLeadershipProfile;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
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
 * K-CHURCHWEB-001C §18 — curated Leadership management: add, edit,
 * reorder, associate an approved same-Church media photo. Deliberately
 * not an org chart and not Church Membership user management — a
 * profile shown publicly does not need (and is not) a Keryon staff
 * account, per §18's explicit instruction.
 */
class WebsiteLeadershipResource extends Resource
{
    protected static ?string $model = WebsiteLeadershipProfile::class;

    protected static ?string $cluster = Website::class;

    protected static ?string $navigationLabel = 'Leadership';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'leadership profile';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make()
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Select::make('category')
                            ->options(LeadershipCategory::options())
                            ->native(false)
                            ->required(),
                        TextInput::make('role_title')
                            ->label('Title')
                            ->placeholder('e.g. Senior Pastor')
                            ->maxLength(255),
                        MediaSelectField::make('photo_id', 'Photo'),
                        TextInput::make('photo_alt_override')
                            ->label('Photo alt text (optional override)')
                            ->helperText("Leave blank to use the photo's own default description.")
                            ->maxLength(255),
                        Textarea::make('bio')
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
                ImageColumn::make('photo.path')
                    ->label('')
                    ->disk(fn (WebsiteLeadershipProfile $record): ?string => $record->photo?->disk)
                    ->circular(),
                TextColumn::make('name')
                    ->weight('semibold')
                    ->searchable(),
                TextColumn::make('category')
                    ->badge()
                    ->formatStateUsing(fn (LeadershipCategory $state): string => $state->label()),
                TextColumn::make('role_title')
                    ->label('Title')
                    ->placeholder('—')
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
            ->emptyStateHeading('No leadership profiles yet')
            ->emptyStateDescription('Leadership profiles can be shown on your church website — add your pastor, ministers, elders, or team.')
            ->emptyStateIcon('heroicon-o-user-group')
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
            'index' => ListWebsiteLeadershipProfiles::route('/'),
        ];
    }
}
