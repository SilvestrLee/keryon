<?php

namespace App\Domain\Provisioning;

use App\Models\ChurchDomain;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Production DomainProvisioner backed by Cloudflare for SaaS (Custom
 * Hostnames API).
 *
 * Idempotency is hostname-based, not key-based: Cloudflare has no concept
 * of a client-supplied idempotency key for this endpoint, so every
 * operation performs its own exact, normalized-hostname lookup before
 * mutating anything. The $idempotencyKey parameter is still accepted (the
 * DomainProvisioner contract requires it) but is not sent to Cloudflare.
 *
 * No Cloudflare-specific identifier is ever persisted to church_domains —
 * every method re-derives the provider object via an exact hostname lookup.
 */
final class CloudflareDomainProvisioner implements DomainProvisioner
{
    private const DUPLICATE_HOSTNAME_ERROR_CODE = 1406;

    /** @var list<string> */
    private const TERMINAL_HOSTNAME_STATUSES = ['blocked', 'deleted', 'test_failed'];

    /** @var list<string> */
    private const PENDING_SSL_STATUSES = [
        'initializing', 'pending_validation', 'pending_issuance', 'pending_deployment', 'staging_deployment',
    ];

    public function requestTlsProvisioning(ChurchDomain $domain, string $idempotencyKey): ProvisioningStatus
    {
        try {
            $config = $this->configuration();
        } catch (ProvisioningConfigurationException) {
            return ProvisioningStatus::Unavailable;
        }

        $hostname = $this->normalizeHostname($domain->normalized_hostname);
        $lookup = $this->exactLookup($config, $hostname);
        if (! $lookup['ok']) {
            return ProvisioningStatus::Unavailable;
        }
        if (count($lookup['matches']) > 1) {
            return ProvisioningStatus::Failed;
        }
        if (count($lookup['matches']) === 1) {
            return $this->statusFromCandidate($lookup['matches'][0]);
        }

        return $this->create($config, $hostname);
    }

    public function checkTlsStatus(ChurchDomain $domain): ProvisioningStatus
    {
        try {
            $config = $this->configuration();
        } catch (ProvisioningConfigurationException) {
            return ProvisioningStatus::Unavailable;
        }

        $hostname = $this->normalizeHostname($domain->normalized_hostname);
        $lookup = $this->exactLookup($config, $hostname);
        if (! $lookup['ok']) {
            return ProvisioningStatus::Unavailable;
        }

        $count = count($lookup['matches']);
        if ($count === 0) {
            // Provisioning was previously requested but the provider object
            // is gone — cannot safely evaluate a status for it.
            return ProvisioningStatus::Unavailable;
        }
        if ($count > 1) {
            return ProvisioningStatus::Failed;
        }

        return $this->statusFromCandidate($lookup['matches'][0]);
    }

    public function deactivate(ChurchDomain $domain): void
    {
        // Configuration failures are not caught here: deactivate() has no
        // status enum to report through, and silently no-op'ing would risk
        // pretending cleanup happened when it did not.
        $config = $this->configuration();

        $hostname = $this->normalizeHostname($domain->normalized_hostname);
        $lookup = $this->exactLookup($config, $hostname);
        if (! $lookup['ok']) {
            throw new ProviderIntegrationException('Cloudflare custom hostname lookup failed during deactivation.');
        }

        $count = count($lookup['matches']);
        if ($count === 0) {
            return; // Already absent — deactivation is idempotent.
        }
        if ($count > 1) {
            throw new ProviderIntegrationException('Cloudflare returned ambiguous custom hostname matches during deactivation.');
        }

        $id = $lookup['matches'][0]['id'] ?? null;
        if (! is_string($id) || $id === '') {
            throw new ProviderIntegrationException('Cloudflare custom hostname is missing its identifier.');
        }

        try {
            $response = $this->client($config)->delete("/zones/{$config['zone_id']}/custom_hostnames/{$id}");
        } catch (ConnectionException $exception) {
            throw new ProviderIntegrationException('Cloudflare custom hostname deletion request failed.', $exception);
        }

        if (! $response->successful()) {
            throw new ProviderIntegrationException('Cloudflare rejected the custom hostname deletion request.');
        }

        $body = $response->json();
        if (! is_array($body) || ($body['success'] ?? null) !== true) {
            throw new ProviderIntegrationException('Cloudflare returned an unexpected deletion response.');
        }
    }

    private function create(array $config, string $hostname): ProvisioningStatus
    {
        // Deliberately no custom_origin_server / custom_origin_sni: this
        // hostname routes through the zone's Fallback Origin (Cloudflare for
        // SaaS), zone-level infrastructure this adapter does not configure.
        // See K-DOMAIN-001G-A §2/§5/§7.
        $payload = [
            'hostname' => $hostname,
            'ssl' => [
                'method' => $config['validation_method'],
                'type' => 'dv',
                'settings' => [
                    'min_tls_version' => $config['min_tls_version'],
                ],
            ],
        ];

        try {
            $response = $this->client($config)->post("/zones/{$config['zone_id']}/custom_hostnames", $payload);
        } catch (ConnectionException) {
            return ProvisioningStatus::Unavailable;
        }

        if ($response->status() === 409 && $this->isDuplicateHostnameError($response)) {
            return $this->recoverFromDuplicateRace($config, $hostname);
        }

        if (! $response->successful()) {
            return ProvisioningStatus::Unavailable;
        }

        $body = $response->json();
        if (! is_array($body) || ($body['success'] ?? null) !== true || ! is_array($body['result'] ?? null)) {
            return ProvisioningStatus::Unavailable;
        }

        return $this->statusFromCandidate($body['result']);
    }

    private function recoverFromDuplicateRace(array $config, string $hostname): ProvisioningStatus
    {
        $lookup = $this->exactLookup($config, $hostname);
        if (! $lookup['ok']) {
            return ProvisioningStatus::Unavailable;
        }

        $count = count($lookup['matches']);
        if ($count === 1) {
            return $this->statusFromCandidate($lookup['matches'][0]);
        }
        if ($count > 1) {
            return ProvisioningStatus::Failed; // Ambiguous — fail closed.
        }

        // Cloudflare reported a duplicate but a fresh exact lookup found
        // none: an inconsistent provider state we cannot safely resolve.
        return ProvisioningStatus::Unavailable;
    }

    private function isDuplicateHostnameError(Response $response): bool
    {
        $errors = $response->json('errors');
        if (! is_array($errors)) {
            return false;
        }

        foreach ($errors as $error) {
            if (is_array($error) && (int) ($error['code'] ?? 0) === self::DUPLICATE_HOSTNAME_ERROR_CODE) {
                return true;
            }
        }

        return false;
    }

    /**
     * Defensively verify exact, normalized-hostname matches ourselves —
     * never trust Cloudflare's filter alone, and never choose "the first
     * result" when more than one candidate comes back.
     *
     * @return array{ok: bool, matches: list<array<string, mixed>>}
     */
    private function exactLookup(array $config, string $hostname): array
    {
        $matches = [];
        $page = 1;
        $totalPages = 1;

        do {
            try {
                $response = $this->client($config)->get("/zones/{$config['zone_id']}/custom_hostnames", [
                    'hostname' => $hostname,
                    'per_page' => 50,
                    'page' => $page,
                ]);
            } catch (ConnectionException) {
                return ['ok' => false, 'matches' => []];
            }

            if (! $response->successful()) {
                return ['ok' => false, 'matches' => []];
            }

            $body = $response->json();
            if (! is_array($body) || ($body['success'] ?? null) !== true || ! is_array($body['result'] ?? null)) {
                return ['ok' => false, 'matches' => []];
            }

            foreach ($body['result'] as $candidate) {
                if (is_array($candidate)
                    && is_string($candidate['hostname'] ?? null)
                    && $this->normalizeHostname($candidate['hostname']) === $hostname) {
                    $matches[] = $candidate;
                }
            }

            $totalPages = max(1, (int) ($body['result_info']['total_pages'] ?? 1));
            $page++;
        } while ($page <= $totalPages);

        return ['ok' => true, 'matches' => $matches];
    }

    /** @param array<string, mixed> $candidate */
    private function statusFromCandidate(array $candidate): ProvisioningStatus
    {
        $hostnameStatus = is_string($candidate['status'] ?? null) ? $candidate['status'] : null;
        $sslStatus = is_string($candidate['ssl']['status'] ?? null) ? $candidate['ssl']['status'] : null;

        $hostnameCategory = $this->classifyHostnameStatus($hostnameStatus);
        $sslCategory = $this->classifySslStatus($sslStatus);

        if ($hostnameCategory === 'failed' || $sslCategory === 'failed') {
            return ProvisioningStatus::Failed;
        }
        if ($hostnameCategory === 'active' && $sslCategory === 'active') {
            return ProvisioningStatus::Ready;
        }

        return ProvisioningStatus::Pending;
    }

    private function classifyHostnameStatus(?string $status): string
    {
        if ($status === 'active') {
            return 'active';
        }
        if (in_array($status, self::TERMINAL_HOSTNAME_STATUSES, true)) {
            return 'failed';
        }

        return 'pending';
    }

    private function classifySslStatus(?string $status): string
    {
        if ($status === 'active') {
            return 'active';
        }
        if (in_array($status, self::PENDING_SSL_STATUSES, true)) {
            return 'pending';
        }

        return 'failed';
    }

    private function normalizeHostname(string $hostname): string
    {
        return rtrim(strtolower(trim($hostname)), '.');
    }

    /** @return array{api_base_url: string, zone_id: string, api_token: string, validation_method: string, min_tls_version: string, connect_timeout: int, timeout: int} */
    private function configuration(): array
    {
        $config = config('cloudflare.provisioner');

        foreach (['zone_id', 'api_token', 'api_base_url'] as $key) {
            if (! is_string($config[$key] ?? null) || trim((string) $config[$key]) === '') {
                throw new ProvisioningConfigurationException("Cloudflare provisioner configuration is missing [{$key}].");
            }
        }

        if (! str_starts_with((string) $config['api_base_url'], 'https://')) {
            throw new ProvisioningConfigurationException('Cloudflare API base URL must use HTTPS.');
        }

        $method = (string) ($config['validation_method'] ?? 'http');
        if (! in_array($method, ['http', 'txt', 'email'], true)) {
            throw new ProvisioningConfigurationException('Cloudflare SSL validation method is not recognized.');
        }

        $minTlsVersion = (string) ($config['min_tls_version'] ?? '1.2');
        if (! in_array($minTlsVersion, ['1.0', '1.1', '1.2', '1.3'], true)) {
            throw new ProvisioningConfigurationException('Cloudflare minimum TLS version is not recognized.');
        }

        return [
            'api_base_url' => (string) $config['api_base_url'],
            'zone_id' => (string) $config['zone_id'],
            'api_token' => (string) $config['api_token'],
            'validation_method' => $method,
            'min_tls_version' => $minTlsVersion,
            'connect_timeout' => (int) ($config['connect_timeout'] ?? 5),
            'timeout' => (int) ($config['timeout'] ?? 15),
        ];
    }

    /** @param array{api_base_url: string, zone_id: string, api_token: string, connect_timeout: int, timeout: int} $config */
    private function client(array $config): PendingRequest
    {
        return Http::baseUrl(rtrim($config['api_base_url'], '/'))
            ->withToken($config['api_token'])
            ->acceptJson()
            ->connectTimeout($config['connect_timeout'])
            ->timeout($config['timeout']);
    }
}
