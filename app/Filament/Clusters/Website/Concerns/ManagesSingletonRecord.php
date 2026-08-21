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

        if ($this->record) {
            Gate::authorize('update', $this->record);
            $this->record->update($data);
        } else {
            Gate::authorize('create', static::modelClass());
            $this->record = static::modelClass()::create($data);
        }

        Notification::make()
            ->title('Saved')
            ->success()
            ->send();
    }
}
