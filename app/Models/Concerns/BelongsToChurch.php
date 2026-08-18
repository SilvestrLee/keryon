<?php

namespace App\Models\Concerns;

use App\Models\Church;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToChurch
{
    /**
     * Tenant ID source: TenantContext, resolved from the active
     * ChurchMembership — not a direct Auth::user()->church_id read.
     * See Keryon_Identity_Membership_Authorization_Architecture_v1.4.1.md
     * §3. The fail-closed mechanics below are unchanged from before that
     * conversion — only the source of the church ID changed.
     */
    protected static function bootBelongsToChurch(): void
    {
        static::creating(function ($model) {
            $churchId = app(TenantContext::class)->currentChurchId();

            if ($churchId && empty($model->church_id)) {
                $model->church_id = $churchId;
            }
        });

        static::addGlobalScope('church_tenant', function (Builder $query) {
            $churchId = app(TenantContext::class)->currentChurchId();

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

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function scopeForChurch(Builder $query, int $churchId): Builder
    {
        return $query->where('church_id', $churchId);
    }
}
