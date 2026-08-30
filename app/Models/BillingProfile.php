<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingProfile extends Model
{
    protected $fillable = ['billing_account_id', 'billing_name', 'legal_name', 'billing_email', 'address_line_1', 'address_line_2', 'city', 'region', 'postal_code', 'country_code', 'preferred_currency', 'tax_identifier_type', 'tax_identifier_value', 'effective_from', 'effective_until'];

    protected function casts(): array
    {
        return ['tax_identifier_value' => 'encrypted', 'effective_from' => 'datetime', 'effective_until' => 'datetime'];
    }

    public function billingAccount(): BelongsTo
    {
        return $this->belongsTo(BillingAccount::class);
    }

    public function snapshot(): array
    {
        return collect($this->attributesToArray())->only(['billing_name', 'legal_name', 'billing_email', 'address_line_1', 'address_line_2', 'city', 'region', 'postal_code', 'country_code', 'preferred_currency', 'tax_identifier_type'])->all();
    }
}
