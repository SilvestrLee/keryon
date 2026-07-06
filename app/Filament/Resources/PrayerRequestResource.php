<?php

namespace App\Filament\Resources;

use App\Enums\PrayerRequestStatus;

use App\Filament\Resources\PrayerRequestResource\Pages\EditPrayerRequest;
use App\Filament\Resources\PrayerRequestResource\Pages\ListPrayerRequests;
use App\Filament\Resources\PrayerRequestResource\Pages\ViewPrayerRequest;
use App\Models\CongregationMember;
use App\Models\PrayerRequest;
use App\Support\CurrentChurch;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PrayerRequestResource extends Resource
{
    protected static ?string $model = PrayerRequest::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Care Center';

    protected static ?string $navigationLabel = 'Prayer Requests';

    protected static ?string $modelLabel = 'Prayer Request';

    protected static ?string $pluralModelLabel = 'Prayer Requests';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-heart';

    protected static ?int $navigationSort = 1;

    public static function visibilityOptions(): array
    {
        return [
            'private' => 'Private',
            'care_team' => 'Care Team',
            'public_testimony' => 'Public Testimony Candidate',
        ];
    }

    public static function visibilityColor(string $state): string
    {
        return match ($state) {
            'care_team' => 'info',
            'public_testimony' => 'success',
            default => 'gray',
        };
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Requester')
                    ->schema([
                        Select::make('congregation_member_id')
                            ->label('Congregation Member')
                            ->relationship('congregationMember', 'first_name')
                            ->getOptionLabelFromRecordUsing(fn (CongregationMember $record): string => $record->full_name)
                            ->searchable()
                            ->nullable(),
                        TextInput::make('requester_name')
                            ->label('Requester Name')
                            ->nullable(),
                        TextInput::make('requester_email')
                            ->label('Requester Email')
                            ->email()
                            ->nullable(),
                        TextInput::make('requester_phone')
                            ->label('Requester Phone')
                            ->nullable(),
                    ])
                    ->columns(2),
                Section::make('Prayer Request')
                    ->schema([
                        TextInput::make('title')
                            ->label('Title')
                            ->nullable(),
                        Textarea::make('request')
                            ->label('Prayer Request')
                            ->required()
                            ->helperText('Record the request clearly and respectfully.')
                            ->columnSpanFull(),
                        Select::make('visibility')
                            ->label('Visibility')
                            ->options(self::visibilityOptions())
                            ->default('private')
                            ->helperText('Visibility controls who should be allowed to see this request later. It does not publish anything publicly.')
                            ->required(),
                        DateTimePicker::make('submitted_at')
                            ->label('Submitted At')
                            ->nullable(),
                    ])
                    ->columns(2),
                Section::make('Care Status')
                    ->schema([
                        Select::make('status')
                            ->label('Status')
                            ->options(PrayerRequestStatus::options())
                            ->default(PrayerRequestStatus::NEW->value)
                            ->required(),
                        DateTimePicker::make('closed_at')
                            ->label('Closed At')
                            ->nullable(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Prayer Request Summary')
                    ->schema([
                        TextEntry::make('title')
                            ->label('Title')
                            ->placeholder('—'),
                        TextEntry::make('request')
                            ->label('Prayer Request')
                            ->columnSpanFull(),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (PrayerRequestStatus $state): string => $state->label())
                            ->color(fn (PrayerRequestStatus $state): string => $state->color()),
                        TextEntry::make('visibility')
                            ->label('Visibility')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => self::visibilityOptions()[$state] ?? $state)
                            ->color(fn (string $state): string => self::visibilityColor($state)),
                    ])
                    ->columns(2),
                Section::make('Requester Information')
                    ->schema([
                        TextEntry::make('requester_name')
                            ->label('Requester Name')
                            ->placeholder('—'),
                        TextEntry::make('requester_email')
                            ->label('Requester Email')
                            ->placeholder('—'),
                        TextEntry::make('requester_phone')
                            ->label('Requester Phone')
                            ->placeholder('—'),
                        TextEntry::make('congregationMember.full_name')
                            ->label('Congregation Member')
                            ->placeholder('—'),
                    ])
                    ->columns(2),
                Section::make('Care Status')
                    ->schema([
                        TextEntry::make('submitted_at')
                            ->label('Submitted At')
                            ->dateTime()
                            ->placeholder('—'),
                        TextEntry::make('closed_at')
                            ->label('Closed At')
                            ->dateTime()
                            ->placeholder('—'),
                        TextEntry::make('church.name')
                            ->label('Church'),
                    ])
                    ->columns(2),
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
                TextColumn::make('title')
                    ->label('Title')
                    ->formatStateUsing(fn (?string $state, PrayerRequest $record): string => filled($state) ? $state : str($record->request)->limit(40)->toString())
                    ->searchable(),
                TextColumn::make('requester_name')
                    ->label('Requester')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('congregationMember.full_name')
                    ->label('Congregation Member')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (PrayerRequestStatus $state): string => $state->label())
                    ->color(fn (PrayerRequestStatus $state): string => $state->color()),
                TextColumn::make('visibility')
                    ->label('Visibility')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::visibilityOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => self::visibilityColor($state)),
                TextColumn::make('submitted_at')
                    ->label('Submitted At')
                    ->dateTime('M j, Y g:ia')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime('M j, Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(PrayerRequestStatus::options()),
                SelectFilter::make('visibility')
                    ->options(self::visibilityOptions()),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No prayer requests yet.')
            ->emptyStateDescription(fn (): string => 'Prayer requests for ' . CurrentChurch::name() . ' will appear here.')
            ->emptyStateIcon('heroicon-o-heart')
            ->emptyStateActions([
                CreateAction::make()
                    ->label('Add Prayer Request')
                    ->slideOver(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPrayerRequests::route('/'),
            'view' => ViewPrayerRequest::route('/{record}'),
            'edit' => EditPrayerRequest::route('/{record}/edit'),
        ];
    }
}
