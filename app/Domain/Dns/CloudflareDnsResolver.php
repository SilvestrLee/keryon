<?php

namespace App\Domain\Dns;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Production DnsResolver backed by Cloudflare DNS-over-HTTPS.
 *
 * Cloudflare's JSON DoH response shape is provider-specific, not an IETF
 * wire standard — that parsing is kept entirely inside this adapter so
 * the rest of Keryon only ever sees DnsLookupResult/DnsAddressResult.
 *
 * Fail-closed: any transport failure, non-2xx response, or malformed
 * payload maps to Unavailable/Timeout — never Found or NotFound. Those two
 * statuses are reserved for a genuinely conclusive answer from Cloudflare.
 */
final class CloudflareDnsResolver implements DnsResolver
{
    private const TYPE_A = 1;

    private const TYPE_CNAME = 5;

    private const TYPE_TXT = 16;

    private const TYPE_AAAA = 28;

    public function txt(string $hostname): DnsLookupResult
    {
        return $this->lookup($hostname, self::TYPE_TXT, $this->parseTxtValue(...));
    }

    public function cname(string $hostname): DnsLookupResult
    {
        return $this->lookup($hostname, self::TYPE_CNAME, $this->parseCnameValue(...));
    }

    public function addresses(string $hostname): DnsAddressResult
    {
        $ipv4 = $this->lookup($hostname, self::TYPE_A, fn (string $data): ?string => $this->parseIp($data, false));
        $ipv6 = $this->lookup($hostname, self::TYPE_AAAA, fn (string $data): ?string => $this->parseIp($data, true));

        return $this->combineAddresses($ipv4, $ipv6);
    }

    /** @param callable(string): ?string $parseValue */
    private function lookup(string $hostname, int $type, callable $parseValue): DnsLookupResult
    {
        $query = $this->normalizeQueryName($hostname);

        try {
            $response = $this->client()->get($this->endpoint(), ['name' => $query, 'type' => $type]);
        } catch (ConnectionException $exception) {
            return $this->isTimeout($exception) ? DnsLookupResult::timeout() : DnsLookupResult::unavailable();
        }

        if (! $response->successful()) {
            return DnsLookupResult::unavailable();
        }

        $body = $response->json();
        if (! is_array($body) || ! is_int($body['Status'] ?? null)) {
            return DnsLookupResult::unavailable();
        }

        if ($body['Status'] === 3) { // NXDOMAIN
            return DnsLookupResult::notFound();
        }
        if ($body['Status'] !== 0) { // SERVFAIL and other non-success RCODEs
            return DnsLookupResult::unavailable();
        }

        $answers = $body['Answer'] ?? [];
        if (! is_array($answers)) {
            return DnsLookupResult::unavailable();
        }

        $matching = array_values(array_filter(
            $answers,
            fn ($answer): bool => is_array($answer) && ($answer['type'] ?? null) === $type,
        ));

        if ($matching === []) {
            // NOERROR with no answer of the requested type is genuine absence
            // (NODATA), not a provider failure.
            return DnsLookupResult::notFound();
        }

        $values = [];
        foreach ($matching as $answer) {
            if (! is_string($answer['data'] ?? null)) {
                // An answer entry that cannot be trusted must not be
                // silently dropped — the whole response becomes unusable.
                return DnsLookupResult::unavailable();
            }
            $value = $parseValue($answer['data']);
            if ($value === null) {
                return DnsLookupResult::unavailable();
            }
            $values[] = $value;
        }

        return DnsLookupResult::found(array_values(array_unique($values)));
    }

    /**
     * An operational failure in either address family must remain visible
     * even when the other family returned usable addresses — routing
     * verification must never be allowed to treat incomplete A/AAAA
     * evidence as conclusive. Precedence (highest wins): Timeout >
     * Unavailable > Found > NotFound. NotFound is not an operational
     * failure, so it never masks — or is masked by anything except a
     * genuine failure on the other side.
     */
    private function combineAddresses(DnsLookupResult $ipv4, DnsLookupResult $ipv6): DnsAddressResult
    {
        if ($ipv4->status === DnsLookupStatus::Timeout || $ipv6->status === DnsLookupStatus::Timeout) {
            return new DnsAddressResult(DnsLookupStatus::Timeout);
        }

        if ($ipv4->status === DnsLookupStatus::Unavailable || $ipv6->status === DnsLookupStatus::Unavailable) {
            return new DnsAddressResult(DnsLookupStatus::Unavailable);
        }

        if ($ipv4->status === DnsLookupStatus::Found || $ipv6->status === DnsLookupStatus::Found) {
            return new DnsAddressResult(
                DnsLookupStatus::Found,
                $ipv4->status === DnsLookupStatus::Found ? $ipv4->values : [],
                $ipv6->status === DnsLookupStatus::Found ? $ipv6->values : [],
            );
        }

        return new DnsAddressResult(DnsLookupStatus::NotFound);
    }

    private function normalizeQueryName(string $hostname): string
    {
        return rtrim(strtolower(trim($hostname)), '.');
    }

    private function parseCnameValue(string $data): ?string
    {
        $value = rtrim(strtolower(trim($data)), '.');

        return $value === '' ? null : $value;
    }

    private function parseIp(string $data, bool $ipv6): ?string
    {
        $flag = $ipv6 ? FILTER_FLAG_IPV6 : FILTER_FLAG_IPV4;
        if (filter_var($data, FILTER_VALIDATE_IP, $flag) === false) {
            return null;
        }

        $packed = @inet_pton($data);
        if ($packed === false) {
            return null;
        }

        return inet_ntop($packed);
    }

    /**
     * Cloudflare returns TXT record data as one or more quoted DNS
     * character-strings, e.g. "\"token-value\"". Concatenate every quoted
     * segment and unescape backslash-escaped characters. Numeric \DDD
     * escapes are not expanded — not needed for Keryon's plain-token
     * ownership values, and this stays a documented adapter limitation.
     */
    private function parseTxtValue(string $data): ?string
    {
        $trimmed = trim($data);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"/', $trimmed, $matches) === 0) {
            return null;
        }

        $decoded = '';
        foreach ($matches[1] as $segment) {
            $decoded .= preg_replace_callback('/\\\\(.)/', fn (array $m): string => $m[1], $segment);
        }

        return $decoded;
    }

    private function isTimeout(ConnectionException $exception): bool
    {
        return str_contains(strtolower($exception->getMessage()), 'timed out')
            || str_contains(strtolower($exception->getMessage()), 'timeout');
    }

    private function endpoint(): string
    {
        return (string) (config('cloudflare.dns.endpoint') ?? 'https://cloudflare-dns.com/dns-query');
    }

    private function client(): PendingRequest
    {
        $config = config('cloudflare.dns');

        $request = Http::withHeaders(['Accept' => 'application/dns-json'])
            ->connectTimeout((int) ($config['connect_timeout'] ?? 3))
            ->timeout((int) ($config['timeout'] ?? 5));

        $retryTimes = (int) ($config['retry_times'] ?? 0);
        if ($retryTimes > 0) {
            $request = $request->retry($retryTimes, (int) ($config['retry_delay_ms'] ?? 0), throw: false);
        }

        return $request;
    }
}
