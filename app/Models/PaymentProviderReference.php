<?php

namespace App\Models;

use App\Enums\PaymentProviderReferenceType;
use Illuminate\Database\Eloquent\Model;

class PaymentProviderReference extends Model
{
    protected $fillable = ['provider', 'provider_account_key', 'reference_type', 'provider_reference', 'local_type', 'local_id'];

    protected function casts(): array
    {
        return ['reference_type' => PaymentProviderReferenceType::class];
    }
}
