<?php

namespace App\Billing\Providers\Paystack;

use App\Billing\Providers\NormalizedPaymentEvidence;
use Carbon\CarbonImmutable;
use DomainException;

class PaystackPaymentNormalizer
{
    public function normalize(array $transaction, string $payloadHash): NormalizedPaymentEvidence
    {
        foreach (['id', 'reference', 'status', 'amount', 'currency', 'domain'] as $field) {
            if (! array_key_exists($field, $transaction)) {
                throw new DomainException('Verified Paystack transaction is incomplete.');
            }
        }

        if ((! is_int($transaction['amount']) && ! ctype_digit((string) $transaction['amount'])) || (int) $transaction['amount'] <= 0) {
            throw new DomainException('Verified Paystack amount is invalid.');
        }

        if (! is_scalar($transaction['reference']) || (string) $transaction['reference'] === '' || ! is_scalar($transaction['id']) || (string) $transaction['id'] === '') {
            throw new DomainException('Verified Paystack transaction identity is invalid.');
        }

        $currency = strtoupper((string) $transaction['currency']);
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new DomainException('Verified Paystack currency is invalid.');
        }

        return new NormalizedPaymentEvidence('paystack', config('billing.providers.paystack.account_key'), (string) $transaction['domain'], (string) $transaction['reference'], (string) $transaction['id'], (string) $transaction['reference'], (string) $transaction['status'], (int) $transaction['amount'], strtoupper((string) $transaction['currency']), CarbonImmutable::parse($transaction['paid_at'] ?? $transaction['paidAt'] ?? now()), $payloadHash);
    }
}
