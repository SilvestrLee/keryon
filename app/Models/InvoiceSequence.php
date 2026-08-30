<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceSequence extends Model
{
    protected $fillable = ['merchant_legal_entity_id', 'series', 'sequence_year', 'next_number'];

    protected function casts(): array
    {
        return ['sequence_year' => 'integer', 'next_number' => 'integer'];
    }

    public function merchantLegalEntity(): BelongsTo
    {
        return $this->belongsTo(MerchantLegalEntity::class);
    }
}
