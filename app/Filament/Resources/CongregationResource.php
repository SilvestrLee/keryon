<?php

namespace App\Filament\Resources;

use App\Enums\CongregationStatus;

use App\Support\CurrentChurch;
use App\Filament\Resources\CongregationResource\Pages\EditCongregationMember;
use App\Filament\Resources\CongregationResource\Pages\ListCongregationMembers;
use App\Filament\Resources\CongregationResource\Pages\ViewCongregationMember;
use App\Models\CongregationMember;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CongregationResource extends Resource
{
    protected static ?string $model = CongregationMember::class;

    protected static string|\UnitEnum|null $navigationGroup = 'People';

    protected static ?string $navigationLabel = 'Congregation';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('first_name')->required(),
                TextInput::make('last_name')->required(),
                TextInput::make('phone')->required(),
                TextInput::make('email')->email()->nullable(),
                DatePicker::make('birthday')->nullable(),
                Select::make('status')
                    ->options(CongregationStatus::options())
                    ->default(CongregationStatus::ACTIVE->value)
                    ->required(),
            ])
            ->columns(2);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Member Summary')
                    ->schema([
                        TextEntry::make('full_name')
                            ->label('Full Name'),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (CongregationStatus $state): string => $state->label())
                            ->color(fn (CongregationStatus $state): string => $state->color()),
                        TextEntry::make('birthday')
                            ->date('M j, Y'),
                    ])
                    ->columns(3),
                Section::make('Contact Information')
                    ->schema([
                        TextEntry::make('phone'),
                        TextEntry::make('email'),
                    ])
                    ->columns(2),
                Section::make('Church Information')
                    ->schema([
                        TextEntry::make('church.name')
                            ->label('Church'),
                    ]),
                Section::make('Record Information')
                    ->schema([
                        TextEntry::make('created_at')
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->dateTime(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // TODO: Switch to $record->getFirstMediaUrl('avatar') when Spatie Media Library is integrated
                ImageColumn::make('avatar')
                    ->label('Photo')
                    ->circular()
                    ->getStateUsing(
                        fn ($record) => 'https://ui-avatars.com/api/?name='
                            . urlencode($record->first_name . '+' . $record->last_name)
                            . '&background=1E5631&color=fff&size=64'
                    ),
                TextColumn::make('first_name')
                    ->label('Name')
                    ->formatStateUsing(fn ($state, $record) => $state . ' ' . $record->last_name)
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('birthday')
                    ->date('M j, Y')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (CongregationStatus $state): string => $state->label())
                    ->color(fn (CongregationStatus $state): string => $state->color()),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(CongregationStatus::options()),
                Filter::make('birthdays_this_month')
                    ->label('Birthdays This Month')
                    ->query(fn (Builder $query) => $query
                        ->whereNotNull('birthday')
                        ->whereMonth('birthday', now()->month)),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No members yet.')
            ->emptyStateDescription(fn (): string => 'Start building ' . CurrentChurch::possessiveName() . ' congregation directory.')
            ->emptyStateIcon('heroicon-o-users')
            ->emptyStateActions([
                CreateAction::make()
                    ->label('Add First Member')
                    ->slideOver(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCongregationMembers::route('/'),
            'view' => ViewCongregationMember::route('/{record}'),
            'edit' => EditCongregationMember::route('/{record}/edit'),
        ];
    }
}

