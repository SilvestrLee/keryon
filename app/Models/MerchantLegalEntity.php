<?php

namespace App\Models;

use App\Enums\MerchantLegalEntityStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MerchantLegalEntity extends Model
{
    protected $fillable = ['code', 'display_name', 'legal_name', 'country_code', 'status', 'invoice_series'];

    protected function casts(): array
    {
        return ['status' => MerchantLegalEntityStatus::class];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->uuid ??= (string) Str::uuid());
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
