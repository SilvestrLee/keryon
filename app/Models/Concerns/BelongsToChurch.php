<?php

namespace App\Models\Concerns;

use App\Models\Church;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait BelongsToChurch
{
    protected static function bootBelongsToChurch(): void
    {
        static::creating(function ($model) {
            if (Auth::check() && Auth::user()?->church_id && empty($model->church_id)) {
                $model->church_id = Auth::user()->church_id;
            }
        });

        if (! app()->runningInConsole()) {
            static::addGlobalScope('church_tenant', function (Builder $query) {
                $churchId = Auth::check() ? Auth::user()?->church_id : null;

                if (! $churchId) {
                    $query->whereRaw('0 = 1');
                    return;
                }

                $query->where(
                    $query->getModel()->getTable() . '.church_id',
                    $churchId
                );
            });
        }
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function scopeForChurch(Builder $query, int $churchId): Builder
    {
        return $query->where('church_id', $churchId);
    }
}
