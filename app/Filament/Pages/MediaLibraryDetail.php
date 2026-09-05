<?php

namespace App\Filament\Pages;

use App\Media\ChurchMediaLibraryQuery;
use App\Media\DeleteMediaAsset;
use App\Models\MediaAsset;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Auth\Access\AuthorizationException;
use LogicException;
use Throwable;

/**
 * K-MEDIA-V1-001B §16 — the Media detail surface. Deletion always routes
 * through `DeleteMediaAsset::handle()` unchanged (never `$asset->delete()`
 * directly), so the existing Website-publication delete guard, soft
 * delete, and historical Campaign/Design association preservation are
 * inherited automatically rather than re-implemented here.
 */
class MediaLibraryDetail extends Page
{
    protected string $view = 'filament.pages.media-library-detail';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'media/{asset}';

    protected static ?string $title = 'Media';

    public string $asset;

    private ?MediaAsset $assetCache = null;

    public function mount(): void
    {
        // Fails closed (404) for a cross-Church or unknown uuid via
        // ChurchMediaLibraryQuery's own tenant-scoped lookup.
        $this->record();
    }

    public function record(): MediaAsset
    {
        return $this->assetCache ??= app(ChurchMediaLibraryQuery::class)->findByUuid($this->asset);
    }

    public function getTitle(): string
    {
        return $this->record()->original_filename;
    }

    public function backUrl(): string
    {
        return MediaLibrary::getUrl();
    }

    public function previewUrl(): string
    {
        return route('media.private', ['asset' => $this->record()->uuid]);
    }

    public function rightsSummary(): array
    {
        return app(ChurchMediaLibraryQuery::class)->rightsSummary($this->record());
    }

    /** @return list<string> */
    public function usage(): array
    {
        return app(ChurchMediaLibraryQuery::class)->usageFor($this->record());
    }

    public function humanFileSize(): string
    {
        $bytes = $this->record()->size;

        if ($bytes < 1024) {
            return "{$bytes} B";
        }

        $units = ['KB', 'MB', 'GB'];
        $value = $bytes / 1024;
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return round($value, 1).' '.$units[$unit];
    }

    public function humanFileType(): string
    {
        return match ($this->record()->mime_type) {
            'image/jpeg' => 'JPEG image',
            'image/png' => 'PNG image',
            'image/webp' => 'WebP image',
            default => $this->record()->mime_type,
        };
    }

    public function canManage(): bool
    {
        return auth()->user()?->can('update', $this->record()) ?? false;
    }

    public function editAltTextAction(): Action
    {
        return Action::make('editAltText')
            ->label('Edit alt text')
            ->icon('heroicon-o-pencil-square')
            ->authorize(fn (): bool => auth()->user()?->can('update', $this->record()) ?? false)
            ->modalHeading('Edit alt text')
            ->fillForm(fn (): array => ['alt_text' => $this->record()->alt_text])
            ->schema([
                Textarea::make('alt_text')
                    ->label('Alt text')
                    ->helperText('Describes the image for screen readers — used whenever this image is shown without a more specific caption.')
                    ->maxLength(255)
                    ->rows(3),
            ])
            ->action(function (array $data): void {
                $this->record()->forceFill(['alt_text' => $data['alt_text'] ?: null])->save();
                $this->assetCache = null;
                Notification::make()->success()->title('Alt text updated')->send();
            });
    }

    public function deleteMediaAction(): Action
    {
        return Action::make('deleteMedia')
            ->label('Delete')
            ->color('danger')
            ->icon('heroicon-o-trash')
            ->authorize(fn (): bool => auth()->user()?->can('delete', $this->record()) ?? false)
            ->requiresConfirmation()
            ->modalHeading('Delete this media?')
            ->modalDescription('This removes it from your Media Library. It will no longer be available to select for new work.')
            ->modalSubmitActionLabel('Delete')
            ->action(function (): void {
                try {
                    app(DeleteMediaAsset::class)->handle($this->record());
                } catch (AuthorizationException $e) {
                    throw $e;
                } catch (LogicException $e) {
                    // K-MEDIA-V1-001B §27 — DeleteMediaAsset's own
                    // LogicException today is exactly this one case
                    // (an active Website publication reference); translate
                    // it to the exact product-level message the directive
                    // requires rather than leaking the raw exception text.
                    Notification::make()
                        ->danger()
                        ->title('This image could not be deleted')
                        ->body('This image is currently used by your published Website. Remove or replace it there before deleting it.')
                        ->send();

                    return;
                } catch (Throwable $e) {
                    report($e);
                    Notification::make()->danger()->title('This image could not be deleted')->send();

                    return;
                }

                Notification::make()->success()->title('Media deleted')->send();
                $this->redirect(MediaLibrary::getUrl());
            });
    }
}
