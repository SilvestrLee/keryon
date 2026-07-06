<?php

namespace App\Filament\Pages;

use App\Enums\PrayerRequestStatus;
use App\Filament\Resources\PrayerRequestResource;
use App\Models\PrayerRequest;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;

class CareCenterDashboard extends Page
{
    protected string $view = 'filament.pages.care-center-dashboard';

    protected static string|\UnitEnum|null $navigationGroup = 'Care Center';

    protected static ?string $navigationLabel = 'Overview';

    protected static ?string $title = 'Care Center';

    protected static ?string $slug = 'care-center';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?int $navigationSort = 0;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addPrayerRequest')
                ->label('Add Prayer Request')
                ->icon('heroicon-o-plus')
                ->url(fn (): string => PrayerRequestResource::getUrl('index')),
            Action::make('viewAllPrayerRequests')
                ->label('View All Prayer Requests')
                ->icon('heroicon-o-heart')
                ->color('gray')
                ->url(fn (): string => PrayerRequestResource::getUrl('index')),
        ];
    }

    public function getStatusCounts(): array
    {
        return collect(PrayerRequestStatus::cases())
            ->mapWithKeys(fn (PrayerRequestStatus $status): array => [
                $status->value => PrayerRequest::query()->where('status', $status->value)->count(),
            ])
            ->all();
    }

    public function getTotalOpenCount(): int
    {
        return PrayerRequest::query()
            ->where('status', '!=', PrayerRequestStatus::CLOSED->value)
            ->count();
    }

    public function getNeedsAttention(): Collection
    {
        return PrayerRequest::query()
            ->where('status', PrayerRequestStatus::NEW->value)
            ->latest('created_at')
            ->limit(5)
            ->get();
    }

    public function getCurrentlyPraying(): Collection
    {
        return PrayerRequest::query()
            ->where('status', PrayerRequestStatus::PRAYING->value)
            ->latest('created_at')
            ->limit(5)
            ->get();
    }

    public function getRecentlySubmitted(): Collection
    {
        return PrayerRequest::query()
            ->orderByRaw('coalesce(submitted_at, created_at) desc')
            ->limit(5)
            ->get();
    }

    public function statusBadgeClasses(PrayerRequestStatus $status): string
    {
        return $this->colorClasses($status->color());
    }

    public function visibilityBadgeClasses(string $visibility): string
    {
        return $this->colorClasses(PrayerRequestResource::visibilityColor($visibility));
    }

    public function visibilityLabel(string $visibility): string
    {
        return PrayerRequestResource::visibilityOptions()[$visibility] ?? $visibility;
    }

    private function colorClasses(string $color): string
    {
        return match ($color) {
            'warning' => 'bg-amber-50 text-amber-700',
            'info' => 'bg-blue-50 text-blue-700',
            'success' => 'bg-emerald-50 text-emerald-700',
            default => 'bg-gray-100 text-gray-600',
        };
    }
}
