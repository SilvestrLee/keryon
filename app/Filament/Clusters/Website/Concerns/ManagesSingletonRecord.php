<?php

namespace App\Filament\Clusters\Website\Concerns;

use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * K-CHURCHWEB-001C — shared mount/save boilerplate for the one-row-per-
 * Church Website Content pages (Home/About/Contact/Settings/Brand). Each
 * page only declares `modelClass()` and its own `form()` schema; this
 * trait handles find-or-create, authorization, and the save flow
 * identically everywhere so the six pages don't repeat it six times.
 *
 * K-WEB-P0-002 — `save()` re-resolves the existing record itself rather
 * than trusting `$record` as set by `mount()`. `mount()` only runs on
 * the page's initial load; the `save()` action fires as its own,
 * separate Livewire request, which rehydrates a fresh instance of the
 * page class — a protected, non-Livewire-tracked property like `$record`
 * is not part of that rehydration and silently reverts to `null`. That
 * previously made `save()` take the `create()` branch on every save
 * after the first, which crashed with a unique-constraint violation on
 * every one of these tables (`church_id` is unique on all of them) the
 * moment a Church saved already-existing content a second time.
 */
trait ManagesSingletonRecord
{
    use InteractsWithForms;

    public ?array $data = [];

    protected ?Model $record = null;

    abstract public static function modelClass(): string;

    public function mount(): void
    {
        Gate::authorize('viewAny', static::modelClass());

        $this->record = static::modelClass()::query()->first();

        $this->form->fill($this->record?->attributesToArray() ?? []);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $record = static::modelClass()::query()->first();

        if ($record) {
            Gate::authorize('update', $record);
            $record->update($data);
        } else {
            Gate::authorize('create', static::modelClass());
            $record = static::modelClass()::create($data);
        }

        $this->record = $record;

        Notification::make()
            ->title('Saved')
            ->success()
            ->send();
    }
}
