<?php

namespace App\Filament\Resources;

use App\Enums\ContentStatus;
use App\Enums\ContentType;
use App\Filament\Resources\ContentItemResource\Pages\EditContentItem;
use App\Filament\Resources\ContentItemResource\Pages\ListContentItems;
use App\Filament\Resources\ContentItemResource\Pages\ViewContentItem;
use App\Models\ContentItem;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ContentItemResource extends Resource
{
    protected static ?string $model = ContentItem::class;

    protected static ?string $navigationLabel = 'Content Studio';

    protected static ?string $modelLabel = 'Content';

    protected static ?string $pluralModelLabel = 'Content';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 1;

    public static function createContentAction(): CreateAction
    {
        return CreateAction::make()
            ->label('Create Content')
            ->slideOver()
            ->modalWidth(Width::FourExtraLarge)
            ->createAnother(false)
            ->stickyModalHeader()
            ->stickyModalFooter();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Text::make('Editing approved content will return it to Draft so the changes can be reviewed again.')
                    ->weight(FontWeight::Medium)
                    ->color('warning')
                    ->visible(fn (?ContentItem $record): bool => $record?->status === ContentStatus::APPROVED),
                Section::make('Content')
                    ->secondary()
                    ->schema([
                        TextInput::make('title')
                            ->label('Title')
                            ->helperText('An internal working title — this is not published automatically.')
                            ->required()
                            ->maxLength(255),
                        Select::make('content_type')
                            ->label('Content Type')
                            ->options(ContentType::options())
                            ->required(),
                        MarkdownEditor::make('body')
                            ->label('Content')
                            ->required()
                            ->minHeight('24rem')
                            ->columnSpanFull(),
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
                    ->weight('semibold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('excerpt')
                    ->label('Preview')
                    ->getStateUsing(fn (ContentItem $record): string => trim(preg_replace(
                        '/\s+/',
                        ' ',
                        strip_tags(Str::markdown($record->body ?? ''))
                    )))
                    ->limit(100)
                    ->color('gray')
                    ->visibleFrom('md'),
                TextColumn::make('body')
                    ->searchable()
                    ->hidden(),
                TextColumn::make('content_type')
                    ->label('Type')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (ContentType $state): string => $state->label())
                    ->visibleFrom('md'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (ContentStatus $state): string => $state->label())
                    ->color(fn (ContentStatus $state): string => $state->color()),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->visibleFrom('lg'),
                TextColumn::make('updater.name')
                    ->label('Updated By')
                    ->placeholder('—')
                    ->visibleFrom('lg'),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(ContentStatus::options()),
                SelectFilter::make('content_type')
                    ->label('Type')
                    ->options(ContentType::options()),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make()
                        ->modalHeading(fn (ContentItem $record): string => $record->status === ContentStatus::APPROVED
                            ? 'Delete this approved content?'
                            : 'Delete this content?')
                        ->modalDescription(fn (ContentItem $record): ?string => $record->status === ContentStatus::APPROVED
                            ? 'This content has already been approved for use. Deleting it will remove it from Content Studio.'
                            : null),
                ]),
            ])
            ->emptyStateHeading(fn ($livewire): string => match ($livewire->activeTab ?? 'all') {
                'draft' => 'No drafts yet',
                'review' => 'Nothing awaiting review',
                'rejected' => 'Nothing needs changes',
                'approved' => 'No approved content yet',
                default => 'No content yet',
            })
            ->emptyStateDescription(fn ($livewire): string => match ($livewire->activeTab ?? 'all') {
                'draft' => "Drafts you start will stay here until they're ready for review.",
                'review' => 'Content submitted for review will appear here.',
                'rejected' => 'Content sent back with requested changes will appear here.',
                'approved' => "Approved content will appear here once it's ready for use.",
                default => 'Create a draft for an announcement, devotional, social caption, campaign message or other church communication.',
            })
            ->emptyStateIcon('heroicon-o-document-text')
            ->emptyStateActions([
                static::createContentAction(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContentItems::route('/'),
            'view' => ViewContentItem::route('/{record}'),
            'edit' => EditContentItem::route('/{record}/edit'),
        ];
    }
}
