<?php

namespace App\Models;

use App\Enums\PlanStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Plan extends Model
{
    protected $fillable = ['slug', 'name', 'status'];

    protected function casts(): array
    {
        return ['status' => PlanStatus::class];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $plan) => $plan->uuid ??= (string) Str::uuid());
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PlanVersion::class);
    }
}
