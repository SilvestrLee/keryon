<?php

namespace App\Billing\Providers\Paystack;

use App\Billing\Providers\ProviderConfigurationException;

class PaystackSignatureVerifier
{
    public function valid(string $rawBody, ?string $signature): bool
    {
        $secret = config('billing.providers.paystack.secret_key');
        if (! is_string($secret) || $secret === '' || ! is_string($signature) || $signature === '') {
            throw new ProviderConfigurationException('Paystack webhook signing configuration is unavailable.');
        }

        return hash_equals(hash_hmac('sha512', $rawBody, $secret), $signature);
    }
}
