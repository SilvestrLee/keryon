<?php

namespace App\Filament\Resources;

use App\Enums\CongregationStatus;

use App\Filament\Resources\CongregationResource\Pages\EditCongregationMember;
use App\Filament\Resources\CongregationResource\Pages\ListCongregationMembers;
use App\Models\CongregationMember;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
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
                EditAction::make(),
            ])
            ->emptyStateHeading('No members yet.')
            ->emptyStateDescription('Start building your congregation directory.')
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
            'edit' => EditCongregationMember::route('/{record}/edit'),
        ];
    }
}

