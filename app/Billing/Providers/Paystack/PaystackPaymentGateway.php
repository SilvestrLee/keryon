<?php

namespace App\Billing\Providers\Paystack;

use App\Billing\Providers\ProviderConfigurationException;
use App\Billing\Providers\ProviderTransportException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class PaystackPaymentGateway
{
    public function configuration(): array
    {
        $config = config('billing.providers.paystack');
        if (! ($config['enabled'] ?? false)) {
            throw new ProviderConfigurationException('Paystack sandbox adapter is disabled.');
        }
        if (($config['environment'] ?? null) !== 'test') {
            throw new ProviderConfigurationException('Only Paystack test environment is authorized.');
        }
        if (! is_string($config['secret_key'] ?? null) || $config['secret_key'] === '') {
            throw new ProviderConfigurationException('Paystack sandbox secret is not configured.');
        }
        if (! str_starts_with((string) $config['secret_key'], 'sk_test_')) {
            throw new ProviderConfigurationException('A Paystack test secret key is required.');
        }
        if (! str_starts_with((string) $config['base_url'], 'https://api.paystack.co')) {
            throw new ProviderConfigurationException('Paystack base URL is not authorized.');
        }

        return $config;
    }

    public function initialize(array $payload): array
    {
        return $this->request('post', '/transaction/initialize', $payload);
    }

    public function verify(string $reference): array
    {
        return $this->request('get', '/transaction/verify/'.rawurlencode($reference));
    }

    private function request(string $method, string $path, array $payload = []): array
    {
        $config = $this->configuration();
        try {
            $request = Http::baseUrl(rtrim($config['base_url'], '/'))->withToken($config['secret_key'])->acceptJson()
                ->connectTimeout($config['connect_timeout'])->timeout($config['timeout']);
            $response = $method === 'post' ? $request->post($path, $payload) : $request->get($path);
        } catch (ConnectionException) {
            throw new ProviderTransportException('timeout');
        }
        if ($response->status() === 401 || $response->status() === 403) {
            throw new ProviderTransportException('authentication');
        }
        if ($response->serverError()) {
            throw new ProviderTransportException('provider_unavailable');
        }
        if ($response->clientError()) {
            throw new ProviderTransportException('provider_validation');
        }
        $body = $response->json();
        if (! is_array($body) || ($body['status'] ?? false) !== true || ! is_array($body['data'] ?? null)) {
            throw new ProviderTransportException('invalid_response');
        }

        return $body['data'];
    }
}
