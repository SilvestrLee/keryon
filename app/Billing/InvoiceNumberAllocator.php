<?php

namespace App\Billing;

use App\Models\InvoiceSequence;
use App\Models\MerchantLegalEntity;
use Carbon\CarbonImmutable;

class InvoiceNumberAllocator
{
    public function allocate(MerchantLegalEntity $entity, CarbonImmutable $at): string
    {
        $sequence = InvoiceSequence::query()->where('merchant_legal_entity_id', $entity->id)
            ->where('series', $entity->invoice_series)->where('sequence_year', $at->year)->lockForUpdate()->first();
        if (! $sequence) {
            InvoiceSequence::query()->create(['merchant_legal_entity_id' => $entity->id, 'series' => $entity->invoice_series, 'sequence_year' => $at->year, 'next_number' => 1]);
            $sequence = InvoiceSequence::query()->where('merchant_legal_entity_id', $entity->id)->where('series', $entity->invoice_series)->where('sequence_year', $at->year)->lockForUpdate()->firstOrFail();
        }
        $number = $sequence->next_number;
        $sequence->forceFill(['next_number' => $number + 1])->save();

        return sprintf('%s-%d-%06d', $entity->invoice_series, $at->year, $number);
    }
}
