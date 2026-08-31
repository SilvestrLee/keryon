<?php

namespace App\Filament\Resources\ContentItemResource\Pages;

use App\Commercial\Entitlements\EntitlementResolver;
use App\Enums\Capability;
use App\Enums\ContentStatus;
use App\Enums\ContentType;
use App\Enums\EntitlementKey;
use App\Filament\Pages\WebsiteDraftHandoff;
use App\Filament\Resources\ContentItemResource;
use App\Models\ContentItem;
use App\Support\TenantContext;
use App\Website\Drafts\AvailableWebsiteDraftDestinations;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;

class ViewContentItem extends ViewRecord
{
    protected static string $resource = ContentItemResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Content')
                    ->schema([
                        TextEntry::make('title')
                            ->label('Title')
                            ->columnSpanFull(),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (ContentStatus $state): string => $state->label())
                            ->color(fn (ContentStatus $state): string => $state->color()),
                        TextEntry::make('content_type')
                            ->label('Type')
                            ->badge()
                            ->color('gray')
                            ->formatStateUsing(fn (ContentType $state): string => $state->label()),
                        TextEntry::make('body')
                            ->label('Content')
                            ->markdown()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Review Feedback')
                    ->schema([
                        TextEntry::make('rejection_reason')
                            ->label('Changes Requested')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (ContentItem $record): bool => $record->status === ContentStatus::REJECTED),

                Section::make('Approval')
                    ->schema([
                        TextEntry::make('approver.name')
                            ->label('Approved By')
                            ->placeholder('—'),
                        TextEntry::make('approved_at')
                            ->label('Approved At')
                            ->dateTime(),
                    ])
                    ->columns(2)
                    ->visible(fn (ContentItem $record): bool => $record->status === ContentStatus::APPROVED),

                Section::make('Record Information')
                    ->schema([
                        TextEntry::make('creator.name')
                            ->label('Created By')
                            ->placeholder('—'),
                        TextEntry::make('updater.name')
                            ->label('Last Updated By')
                            ->placeholder('—'),
                        TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime(),
                    ])
                    ->columns(3),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('useOnWebsite')
                ->label('Use on Website')
                ->icon('heroicon-o-globe-alt')
                ->visible(function (ContentItem $record): bool {
                    $tenant = app(TenantContext::class);
                    $membership = $tenant->currentMembership();
                    $church = $tenant->currentChurch();

                    return $record->status === ContentStatus::APPROVED
                        && app(AvailableWebsiteDraftDestinations::class)->for($record->content_type) !== []
                        && ($membership?->hasCapability(Capability::WebsiteContentManage) ?? false)
                        && $church !== null
                        && app(EntitlementResolver::class)->allows($church, EntitlementKey::WebsiteEnabled);
                })
                ->url(fn (ContentItem $record): string => WebsiteDraftHandoff::getUrl(['content' => $record->id])),

            Action::make('submitForReview')
                ->label('Submit for Review')
                ->color('primary')
                ->visible(fn (ContentItem $record): bool => in_array($record->status, [ContentStatus::DRAFT, ContentStatus::REJECTED], true))
                ->action(function (ContentItem $record): void {
                    Gate::authorize('submitForReview', $record);

                    $record->submitForReview(auth()->user());

                    Notification::make()
                        ->title('Submitted for review')
                        ->success()
                        ->send();
                }),

            Action::make('approve')
                ->label('Approve')
                ->color('success')
                ->visible(fn (ContentItem $record): bool => $record->status === ContentStatus::REVIEW)
                ->requiresConfirmation()
                ->modalHeading('Approve this content?')
                ->modalDescription("It will be marked ready for use in Keryon's communication workflows.")
                ->modalSubmitActionLabel('Approve')
                ->action(function (ContentItem $record): void {
                    Gate::authorize('approve', $record);

                    $record->approve(auth()->user());

                    Notification::make()
                        ->title('Content approved')
                        ->success()
                        ->send();
                }),

            Action::make('requestChanges')
                ->label('Request Changes')
                ->color('warning')
                ->visible(fn (ContentItem $record): bool => $record->status === ContentStatus::REVIEW)
                ->schema([
                    Textarea::make('reason')
                        ->label('What needs to change?')
                        ->required()
                        ->rows(4),
                ])
                ->modalSubmitActionLabel('Request Changes')
                ->action(function (ContentItem $record, array $data): void {
                    Gate::authorize('requestChanges', $record);

                    $record->requestChanges(auth()->user(), $data['reason']);

                    Notification::make()
                        ->title('Changes requested')
                        ->success()
                        ->send();
                }),

            Action::make('returnToDraft')
                ->label('Return to Draft')
                ->color('gray')
                ->visible(fn (ContentItem $record): bool => $record->status === ContentStatus::REJECTED)
                ->action(function (ContentItem $record): void {
                    Gate::authorize('update', $record);

                    $record->returnToDraft(auth()->user());

                    Notification::make()
                        ->title('Returned to draft')
                        ->success()
                        ->send();
                }),

            EditAction::make(),
        ];
    }
}
