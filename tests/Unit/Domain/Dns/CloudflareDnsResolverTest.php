<?php

namespace Tests\Unit\Domain\Dns;

use App\Domain\Dns\CloudflareDnsResolver;
use App\Domain\Dns\DnsAddressResult;
use App\Domain\Dns\DnsLookupStatus;
use Closure;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CloudflareDnsResolverTest extends TestCase
{
    public function test_txt_lookup_found_decodes_quoted_value(): void
    {
        Http::fake(['cloudflare-dns.com/*' => Http::response($this->dohBody([
            ['type' => 16, 'data' => '"keryon-verify-abc123"'],
        ]))]);

        $result = (new CloudflareDnsResolver)->txt('_keryon-verification.church.example.org');

        $this->assertSame(DnsLookupStatus::Found, $result->status);
        $this->assertSame(['keryon-verify-abc123'], $result->values);
    }

    public function test_txt_parsing_joins_multiple_quoted_segments_and_unescapes(): void
    {
        Http::fake(['cloudflare-dns.com/*' => Http::response($this->dohBody([
            ['type' => 16, 'data' => '"part-one-" "part\\"two"'],
        ]))]);

        $result = (new CloudflareDnsResolver)->txt('_keryon-verification.church.example.org');

        $this->assertSame(DnsLookupStatus::Found, $result->status);
        $this->assertSame(['part-one-part"two'], $result->values);
    }

    public function test_cname_lookup_found_normalizes_trailing_dot_and_case(): void
    {
        Http::fake(['cloudflare-dns.com/*' => Http::response($this->dohBody([
            ['type' => 5, 'data' => 'Ingress.Keryon.App.'],
        ]))]);

        $result = (new CloudflareDnsResolver)->cname('church.example.org');

        $this->assertSame(DnsLookupStatus::Found, $result->status);
        $this->assertSame(['ingress.keryon.app'], $result->values);
    }

    public function test_cname_lookup_removes_duplicate_equivalent_records(): void
    {
        Http::fake(['cloudflare-dns.com/*' => Http::response($this->dohBody([
            ['type' => 5, 'data' => 'ingress.keryon.app.'],
            ['type' => 5, 'data' => 'INGRESS.KERYON.APP'],
        ]))]);

        $result = (new CloudflareDnsResolver)->cname('church.example.org');

        $this->assertSame(['ingress.keryon.app'], $result->values);
    }

    public function test_addresses_found_with_ipv4_only(): void
    {
        Http::fake(function ($request) {
            $type = $this->queryType($request->url());
            if ($type === 1) {
                return Http::response($this->dohBody([['type' => 1, 'data' => '198.51.100.10']]));
            }

            return Http::response($this->dohBody([], status: 3)); // AAAA: NXDOMAIN
        });

        $result = (new CloudflareDnsResolver)->addresses('church.example.org');

        $this->assertSame(DnsLookupStatus::Found, $result->status);
        $this->assertSame(['198.51.100.10'], $result->ipv4);
        $this->assertSame([], $result->ipv6);
    }

    public function test_addresses_found_with_ipv6_only(): void
    {
        Http::fake(function ($request) {
            $type = $this->queryType($request->url());
            if ($type === 28) {
                return Http::response($this->dohBody([['type' => 28, 'data' => '2001:db8::1']]));
            }

            return Http::response($this->dohBody([], status: 3)); // A: NXDOMAIN
        });

        $result = (new CloudflareDnsResolver)->addresses('church.example.org');

        $this->assertSame(DnsLookupStatus::Found, $result->status);
        $this->assertSame([], $result->ipv4);
        $this->assertSame(['2001:db8::1'], $result->ipv6);
    }

    public function test_addresses_invalid_ip_data_is_unavailable(): void
    {
        Http::fake(['cloudflare-dns.com/*' => Http::response($this->dohBody([
            ['type' => 1, 'data' => 'not-an-ip'],
        ]))]);

        $result = (new CloudflareDnsResolver)->addresses('church.example.org');

        $this->assertSame(DnsLookupStatus::Unavailable, $result->status);
    }

    // --- Address-family aggregation must fail closed on operational failure --

    public function test_addresses_ipv4_found_and_ipv6_not_found_is_found(): void
    {
        $result = $this->addressesWith(
            ipv4: Http::response($this->dohBody([['type' => 1, 'data' => '198.51.100.10']])),
            ipv6: Http::response($this->dohBody([], status: 3)), // NXDOMAIN
        );

        $this->assertSame(DnsLookupStatus::Found, $result->status);
    }

    public function test_addresses_ipv4_not_found_and_ipv6_found_is_found(): void
    {
        $result = $this->addressesWith(
            ipv4: Http::response($this->dohBody([], status: 3)), // NXDOMAIN
            ipv6: Http::response($this->dohBody([['type' => 28, 'data' => '2001:db8::1']])),
        );

        $this->assertSame(DnsLookupStatus::Found, $result->status);
    }

    public function test_addresses_ipv4_found_and_ipv6_timeout_does_not_return_found(): void
    {
        $result = $this->addressesWith(
            ipv4: Http::response($this->dohBody([['type' => 1, 'data' => '198.51.100.10']])),
            ipv6: Http::failedConnection('cURL error 28: Operation timed out'),
        );

        $this->assertSame(DnsLookupStatus::Timeout, $result->status);
    }

    public function test_addresses_ipv4_timeout_and_ipv6_found_does_not_return_found(): void
    {
        $result = $this->addressesWith(
            ipv4: Http::failedConnection('cURL error 28: Operation timed out'),
            ipv6: Http::response($this->dohBody([['type' => 28, 'data' => '2001:db8::1']])),
        );

        $this->assertSame(DnsLookupStatus::Timeout, $result->status);
    }

    public function test_addresses_ipv4_found_and_ipv6_unavailable_does_not_return_found(): void
    {
        $result = $this->addressesWith(
            ipv4: Http::response($this->dohBody([['type' => 1, 'data' => '198.51.100.10']])),
            ipv6: Http::response(['error' => 'boom'], 503),
        );

        $this->assertSame(DnsLookupStatus::Unavailable, $result->status);
    }

    public function test_addresses_ipv4_unavailable_and_ipv6_found_does_not_return_found(): void
    {
        $result = $this->addressesWith(
            ipv4: Http::response(['error' => 'boom'], 503),
            ipv6: Http::response($this->dohBody([['type' => 28, 'data' => '2001:db8::1']])),
        );

        $this->assertSame(DnsLookupStatus::Unavailable, $result->status);
    }

    public function test_addresses_timeout_takes_precedence_over_unavailable(): void
    {
        $result = $this->addressesWith(
            ipv4: Http::failedConnection('cURL error 28: Operation timed out'),
            ipv6: Http::response(['error' => 'boom'], 503),
        );

        $this->assertSame(DnsLookupStatus::Timeout, $result->status);
    }

    /**
     * @param  Response|Closure  $ipv4
     * @param  Response|Closure  $ipv6
     */
    private function addressesWith($ipv4, $ipv6): DnsAddressResult
    {
        Http::fake(function ($request) use ($ipv4, $ipv6) {
            $response = $this->queryType($request->url()) === 28 ? $ipv6 : $ipv4;

            return $response instanceof Closure ? $response($request) : $response;
        });

        return (new CloudflareDnsResolver)->addresses('church.example.org');
    }

    public function test_noerror_with_no_matching_answer_is_not_found(): void
    {
        Http::fake(['cloudflare-dns.com/*' => Http::response($this->dohBody([]))]);

        $result = (new CloudflareDnsResolver)->txt('church.example.org');

        $this->assertSame(DnsLookupStatus::NotFound, $result->status);
    }

    public function test_nxdomain_is_not_found(): void
    {
        Http::fake(['cloudflare-dns.com/*' => Http::response($this->dohBody([], status: 3))]);

        $result = (new CloudflareDnsResolver)->txt('church.example.org');

        $this->assertSame(DnsLookupStatus::NotFound, $result->status);
    }

    public function test_servfail_is_unavailable_not_not_found(): void
    {
        Http::fake(['cloudflare-dns.com/*' => Http::response($this->dohBody([], status: 2))]);

        $result = (new CloudflareDnsResolver)->txt('church.example.org');

        $this->assertSame(DnsLookupStatus::Unavailable, $result->status);
    }

    public function test_timeout_is_distinct_from_unavailable_and_not_found(): void
    {
        Http::fake(Http::failedConnection('cURL error 28: Operation timed out after 5000 milliseconds'));

        $result = (new CloudflareDnsResolver)->txt('church.example.org');

        $this->assertSame(DnsLookupStatus::Timeout, $result->status);
    }

    public function test_connection_exception_without_timeout_wording_is_unavailable(): void
    {
        Http::fake(Http::failedConnection('cURL error 6: Could not resolve host'));

        $result = (new CloudflareDnsResolver)->txt('church.example.org');

        $this->assertSame(DnsLookupStatus::Unavailable, $result->status);
    }

    public function test_http_non_2xx_is_unavailable(): void
    {
        Http::fake(['cloudflare-dns.com/*' => Http::response(['error' => 'nope'], 503)]);

        $result = (new CloudflareDnsResolver)->txt('church.example.org');

        $this->assertSame(DnsLookupStatus::Unavailable, $result->status);
    }

    public function test_malformed_json_is_unavailable(): void
    {
        Http::fake(['cloudflare-dns.com/*' => Http::response('not json', 200)]);

        $result = (new CloudflareDnsResolver)->txt('church.example.org');

        $this->assertSame(DnsLookupStatus::Unavailable, $result->status);
    }

    public function test_malformed_answer_entry_is_unavailable(): void
    {
        Http::fake(['cloudflare-dns.com/*' => Http::response($this->dohBody([
            ['type' => 16], // missing "data"
        ]))]);

        $result = (new CloudflareDnsResolver)->txt('church.example.org');

        $this->assertSame(DnsLookupStatus::Unavailable, $result->status);
    }

    public function test_provider_unavailable_never_proves_ownership_or_routing(): void
    {
        Http::fake(['cloudflare-dns.com/*' => Http::response($this->dohBody([], status: 2))]);

        $ownership = (new CloudflareDnsResolver)->txt('_keryon-verification.church.example.org');
        $routing = (new CloudflareDnsResolver)->cname('church.example.org');

        $this->assertNotSame(DnsLookupStatus::Found, $ownership->status);
        $this->assertNotSame(DnsLookupStatus::Found, $routing->status);
    }

    /** @param list<array<string, mixed>> $answers */
    private function dohBody(array $answers, int $status = 0): array
    {
        return ['Status' => $status, 'Answer' => $answers];
    }

    private function queryType(string $url): ?int
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return isset($query['type']) ? (int) $query['type'] : null;
    }
}
