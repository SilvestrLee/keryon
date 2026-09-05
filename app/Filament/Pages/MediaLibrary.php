<?php

namespace App\Filament\Pages;

use App\Enums\AssetProvenance;
use App\Enums\AssetRightsStatus;
use App\Enums\Capability;
use App\Media\ChurchMediaLibraryQuery;
use App\Media\IngestMediaAsset;
use App\Models\MediaAsset;
use App\Support\TenantContext;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Livewire\WithPagination;
use Throwable;

/**
 * K-MEDIA-V1-001B — the general Church Media Library: the missing
 * browsing surface identified in K-MEDIA-V1-001A over an already-mature
 * Media domain. This page deliberately adds no new architecture beyond a
 * read model (`ChurchMediaLibraryQuery`) and a thin upload action that
 * calls the same canonical `IngestMediaAsset` service `MediaSelectField`
 * already uses — every rights, delivery, and deletion invariant already
 * proven in that domain is reused unchanged.
 */
class MediaLibrary extends Page
{
    use WithPagination;

    protected string $view = 'filament.pages.media-library';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static string|\UnitEnum|null $navigationGroup = 'Communications';

    protected static ?string $navigationLabel = 'Media';

    protected static ?string $title = 'Media Library';

    protected static ?string $slug = 'media';

    protected static ?int $navigationSort = 6;

    public string $search = '';

    public string $provenanceFilter = '';

    public string $rightsFilter = '';

    public string $sort = ChurchMediaLibraryQuery::SORT_NEWEST;

    public static function canAccess(): bool
    {
        return app(TenantContext::class)->currentMembership()?->hasCapability(Capability::MediaView) ?? false;
    }

    public function updatedSearch(): void
    {
        $this->resetPage('mediaPage');
    }

    public function updatedProvenanceFilter(): void
    {
        $this->resetPage('mediaPage');
    }

    public function updatedRightsFilter(): void
    {
        $this->resetPage('mediaPage');
    }

    public function updatedSort(): void
    {
        $this->resetPage('mediaPage');
    }

    /** @return LengthAwarePaginator<int, MediaAsset> */
    public function assets(): LengthAwarePaginator
    {
        return app(ChurchMediaLibraryQuery::class)->paginate(
            search: $this->search,
            provenance: filled($this->provenanceFilter) ? AssetProvenance::from($this->provenanceFilter) : null,
            rightsStatus: filled($this->rightsFilter) ? AssetRightsStatus::from($this->rightsFilter) : null,
            sort: $this->sort,
        );
    }

    public function provenanceOptions(): array
    {
        return collect(AssetProvenance::cases())
            ->mapWithKeys(fn (AssetProvenance $case): array => [$case->value => app(ChurchMediaLibraryQuery::class)->provenanceLabel($case)])
            ->all();
    }

    public function rightsOptions(): array
    {
        return [
            AssetRightsStatus::Unverified->value => 'Unverified',
            AssetRightsStatus::Declared->value => 'Declared',
            AssetRightsStatus::Verified->value => 'Verified',
            AssetRightsStatus::Restricted->value => 'Restricted',
            AssetRightsStatus::Expired->value => 'Expired',
            AssetRightsStatus::Disputed->value => 'Disputed',
            AssetRightsStatus::Withdrawn->value => 'Withdrawn',
        ];
    }

    public function provenanceLabel(MediaAsset $asset): string
    {
        return app(ChurchMediaLibraryQuery::class)->rightsSummary($asset)['provenance'];
    }

    public function rightsLabel(MediaAsset $asset): string
    {
        return app(ChurchMediaLibraryQuery::class)->rightsSummary($asset)['statusLabel'];
    }

    public function formattedSize(MediaAsset $asset): string
    {
        return $this->humanFileSize($asset->size);
    }

    public function detailUrl(MediaAsset $asset): string
    {
        return MediaLibraryDetail::getUrl(['asset' => $asset->uuid]);
    }

    public function previewUrl(MediaAsset $asset): string
    {
        return route('media.private', ['asset' => $asset->uuid]);
    }

    public function uploadMediaAction(): Action
    {
        return Action::make('uploadMedia')
            ->label('Upload media')
            ->icon('heroicon-o-arrow-up-tray')
            ->authorize(fn (): bool => auth()->user()?->can('create', MediaAsset::class) ?? false)
            ->modalHeading('Upload media')
            ->modalSubmitActionLabel('Upload')
            ->schema([
                FileUpload::make('upload')
                    ->label('Image')
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(10240)
                    ->disk(config('media.private_disk', 'media-private'))
                    ->directory(fn () => 'tenants/'.app(TenantContext::class)->currentChurchId().'/media/.staging')
                    ->storeFileNamesIn('original_filename')
                    ->required()
                    ->helperText('JPEG, PNG, or WebP — up to 10 MB.'),
                TextInput::make('alt_text')
                    ->label('Alt text')
                    ->helperText('Describes the image for screen readers.')
                    ->maxLength(255),
            ])
            ->action(function (array $data): void {
                try {
                    app(IngestMediaAsset::class)->handle($data['upload'], $data['original_filename'], $data['alt_text'] ?? null);
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title('This image could not be uploaded')->body(collect($e->errors())->flatten()->implode(' '))->send();

                    return;
                } catch (Throwable $e) {
                    report($e);
                    Notification::make()->danger()->title('This image could not be uploaded')->send();

                    return;
                }

                $this->resetPage('mediaPage');
                Notification::make()->success()->title('Media uploaded')->send();
            });
    }

    private function humanFileSize(int $bytes): string
    {
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
}
