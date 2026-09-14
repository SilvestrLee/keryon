<?php

namespace Tests\Unit\Domain\Provisioning;

use App\Domain\Provisioning\CloudflareDomainProvisioner;
use App\Domain\Provisioning\ProviderIntegrationException;
use App\Domain\Provisioning\ProvisioningConfigurationException;
use App\Domain\Provisioning\ProvisioningStatus;
use App\Models\ChurchDomain;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CloudflareDomainProvisionerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cloudflare.provisioner', [
            'api_base_url' => 'https://api.cloudflare.com/client/v4',
            'zone_id' => 'zone-123',
            'api_token' => 'secret-token-value',
            'validation_method' => 'http',
            'min_tls_version' => '1.2',
            'connect_timeout' => 5,
            'timeout' => 15,
        ]);
    }

    private function domain(string $hostname = 'church.example.org'): ChurchDomain
    {
        return new ChurchDomain(['normalized_hostname' => $hostname]);
    }

    private function hostnameObject(string $hostname, string $hostnameStatus, string $sslStatus, string $id = 'ch-1'): array
    {
        return [
            'id' => $id,
            'hostname' => $hostname,
            'status' => $hostnameStatus,
            'ssl' => ['status' => $sslStatus],
        ];
    }

    private function listResponse(array $results, int $totalPages = 1): array
    {
        return [
            'success' => true,
            'errors' => [],
            'messages' => [],
            'result' => $results,
            'result_info' => ['page' => 1, 'per_page' => 50, 'total_pages' => $totalPages, 'count' => count($results)],
        ];
    }

    // --- Request structure -------------------------------------------------

    public function test_create_request_is_zone_scoped_authenticated_and_carries_expected_payload(): void
    {
        Http::fake([
            '*/zones/zone-123/custom_hostnames?*' => Http::response($this->listResponse([])),
            '*/zones/zone-123/custom_hostnames' => Http::response([
                'success' => true,
                'result' => $this->hostnameObject('church.example.org', 'pending', 'initializing'),
            ]),
        ]);

        $status = (new CloudflareDomainProvisioner)->requestTlsProvisioning($this->domain('Church.Example.Org.'), 'key-1');

        $this->assertSame(ProvisioningStatus::Pending, $status);

        Http::assertSent(function ($request) {
            if ($request->method() !== 'POST') {
                return false;
            }
            $this->assertSame('https://api.cloudflare.com/client/v4/zones/zone-123/custom_hostnames', $request->url());
            $this->assertSame('Bearer secret-token-value', $request->header('Authorization')[0]);
            $this->assertSame('church.example.org', $request->data()['hostname']);
            $this->assertArrayNotHasKey('custom_origin_server', $request->data());
            $this->assertArrayNotHasKey('custom_origin_sni', $request->data());
            $this->assertSame('dv', $request->data()['ssl']['type']);
            $this->assertSame('http', $request->data()['ssl']['method']);
            $this->assertSame('1.2', $request->data()['ssl']['settings']['min_tls_version']);

            return true;
        });
    }

    public function test_create_payload_relies_on_zone_fallback_origin_not_per_hostname_custom_origin(): void
    {
        // K-DOMAIN-001G-A §2/§5: V1 routes every custom hostname through the
        // zone's Fallback Origin, never a per-hostname Custom Origin.
        Http::fake([
            '*/zones/zone-123/custom_hostnames?*' => Http::response($this->listResponse([])),
            '*/zones/zone-123/custom_hostnames' => Http::response([
                'success' => true,
                'result' => $this->hostnameObject('church.example.org', 'pending', 'initializing'),
            ]),
        ]);

        (new CloudflareDomainProvisioner)->requestTlsProvisioning($this->domain(), 'key-1');

        Http::assertSent(function ($request) {
            if ($request->method() !== 'POST') {
                return false;
            }
            $keys = array_keys($request->data());
            $this->assertNotContains('custom_origin_server', $keys);
            $this->assertNotContains('custom_origin_sni', $keys);

            return true;
        });
    }

    public function test_absence_of_custom_origin_configuration_does_not_fail_the_provisioner(): void
    {
        // The V1 configuration schema has no custom_origin_server key at
        // all — its absence must never itself be treated as missing
        // configuration (contrast with zone_id/api_token/api_base_url,
        // which remain mandatory).
        config()->set('cloudflare.provisioner', [
            'api_base_url' => 'https://api.cloudflare.com/client/v4',
            'zone_id' => 'zone-123',
            'api_token' => 'secret-token-value',
        ]);

        Http::fake([
            '*/zones/zone-123/custom_hostnames?*' => Http::response($this->listResponse([])),
            '*/zones/zone-123/custom_hostnames' => Http::response([
                'success' => true,
                'result' => $this->hostnameObject('church.example.org', 'pending', 'initializing'),
            ]),
        ]);

        $status = (new CloudflareDomainProvisioner)->requestTlsProvisioning($this->domain(), 'key-1');

        $this->assertSame(ProvisioningStatus::Pending, $status);
    }

    public function test_default_validation_method_is_http_when_unspecified(): void
    {
        config()->set('cloudflare.provisioner', [
            'api_base_url' => 'https://api.cloudflare.com/client/v4',
            'zone_id' => 'zone-123',
            'api_token' => 'secret-token-value',
        ]);

        Http::fake([
            '*/zones/zone-123/custom_hostnames?*' => Http::response($this->listResponse([])),
            '*/zones/zone-123/custom_hostnames' => Http::response([
                'success' => true,
                'result' => $this->hostnameObject('church.example.org', 'pending', 'initializing'),
            ]),
        ]);

        (new CloudflareDomainProvisioner)->requestTlsProvisioning($this->domain(), 'key-1');

        Http::assertSent(fn ($request) => $request->method() !== 'POST' || $request->data()['ssl']['method'] === 'http');
    }

    // --- Exact lookup --------------------------------------------------------

    public function test_zero_exact_matches_proceeds_to_create(): void
    {
        Http::fake([
            '*/zones/zone-123/custom_hostnames?*' => Http::response($this->listResponse([])),
            '*/zones/zone-123/custom_hostnames' => Http::response([
                'success' => true,
                'result' => $this->hostnameObject('church.example.org', 'active', 'active'),
            ]),
        ]);

        $status = (new CloudflareDomainProvisioner)->requestTlsProvisioning($this->domain(), 'key-1');

        $this->assertSame(ProvisioningStatus::Ready, $status);
        Http::assertSentCount(2); // 1 lookup + 1 create
    }

    public function test_one_exact_match_is_idempotent_and_does_not_create(): void
    {
        Http::fake([
            '*/zones/zone-123/custom_hostnames?*' => Http::response($this->listResponse([
                $this->hostnameObject('church.example.org', 'active', 'active'),
            ])),
        ]);

        $status = (new CloudflareDomainProvisioner)->requestTlsProvisioning($this->domain(), 'key-1');

        $this->assertSame(ProvisioningStatus::Ready, $status);
        Http::assertSentCount(1);
        Http::assertNotSent(fn ($request) => $request->method() === 'POST');
    }

    public function test_lookup_defensively_discards_non_exact_hostname_candidates(): void
    {
        Http::fake([
            '*/zones/zone-123/custom_hostnames?*' => Http::response($this->listResponse([
                $this->hostnameObject('other-church.example.org', 'active', 'active'),
                $this->hostnameObject('church.example.org', 'active', 'active', 'ch-real'),
            ])),
        ]);

        $status = (new CloudflareDomainProvisioner)->requestTlsProvisioning($this->domain(), 'key-1');

        $this->assertSame(ProvisioningStatus::Ready, $status);
        Http::assertSentCount(1);
    }

    public function test_more_than_one_exact_match_fails_closed(): void
    {
        Http::fake([
            '*/zones/zone-123/custom_hostnames?*' => Http::response($this->listResponse([
                $this->hostnameObject('church.example.org', 'active', 'active', 'ch-1'),
                $this->hostnameObject('church.example.org', 'active', 'active', 'ch-2'),
            ])),
        ]);

        $status = (new CloudflareDomainProvisioner)->requestTlsProvisioning($this->domain(), 'key-1');

        $this->assertSame(ProvisioningStatus::Failed, $status);
    }

    public function test_exact_lookup_follows_pagination(): void
    {
        Http::fake(function ($request) {
            if ($request->method() !== 'GET') {
                return null;
            }
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $page = (int) ($query['page'] ?? 1);
            if ($page === 1) {
                return Http::response($this->listResponse([
                    $this->hostnameObject('other-church.example.org', 'active', 'active'),
                ], totalPages: 2));
            }

            return Http::response($this->listResponse([
                $this->hostnameObject('church.example.org', 'active', 'active', 'ch-page-2'),
            ], totalPages: 2));
        });

        $status = (new CloudflareDomainProvisioner)->checkTlsStatus($this->domain());

        $this->assertSame(ProvisioningStatus::Ready, $status);
    }

    // --- Create --------------------------------------------------------------

    public function test_duplicate_race_409_1406_recovers_via_exact_relookup(): void
    {
        Http::fake([
            '*/zones/zone-123/custom_hostnames?*' => Http::sequence()
                ->push($this->listResponse([])) // initial lookup: none
                ->push($this->listResponse([$this->hostnameObject('church.example.org', 'active', 'active')])), // relookup: one
            '*/zones/zone-123/custom_hostnames' => Http::response([
                'success' => false,
                'errors' => [['code' => 1406, 'message' => 'Duplicate custom hostname found.']],
            ], 409),
        ]);

        $status = (new CloudflareDomainProvisioner)->requestTlsProvisioning($this->domain(), 'key-1');

        $this->assertSame(ProvisioningStatus::Ready, $status);
    }

    public function test_arbitrary_409_is_not_treated_as_success(): void
    {
        Http::fake([
            '*/zones/zone-123/custom_hostnames?*' => Http::response($this->listResponse([])),
            '*/zones/zone-123/custom_hostnames' => Http::response([
                'success' => false,
                'errors' => [['code' => 9999, 'message' => 'Something else.']],
            ], 409),
        ]);

        $status = (new CloudflareDomainProvisioner)->requestTlsProvisioning($this->domain(), 'key-1');

        $this->assertSame(ProvisioningStatus::Unavailable, $status);
    }

    public function test_malformed_create_response_is_unavailable(): void
    {
        Http::fake([
            '*/zones/zone-123/custom_hostnames?*' => Http::response($this->listResponse([])),
            '*/zones/zone-123/custom_hostnames' => Http::response('not json', 200),
        ]);

        $status = (new CloudflareDomainProvisioner)->requestTlsProvisioning($this->domain(), 'key-1');

        $this->assertSame(ProvisioningStatus::Unavailable, $status);
    }

    public function test_create_http_failure_is_unavailable(): void
    {
        Http::fake([
            '*/zones/zone-123/custom_hostnames?*' => Http::response($this->listResponse([])),
            '*/zones/zone-123/custom_hostnames' => Http::response(['error' => 'boom'], 500),
        ]);

        $status = (new CloudflareDomainProvisioner)->requestTlsProvisioning($this->domain(), 'key-1');

        $this->assertSame(ProvisioningStatus::Unavailable, $status);
    }

    public function test_create_provider_success_false_is_unavailable(): void
    {
        Http::fake([
            '*/zones/zone-123/custom_hostnames?*' => Http::response($this->listResponse([])),
            '*/zones/zone-123/custom_hostnames' => Http::response(['success' => false, 'errors' => []], 200),
        ]);

        $status = (new CloudflareDomainProvisioner)->requestTlsProvisioning($this->domain(), 'key-1');

        $this->assertSame(ProvisioningStatus::Unavailable, $status);
    }

    public function test_create_connection_failure_is_unavailable(): void
    {
        Http::fake([
            '*/zones/zone-123/custom_hostnames?*' => Http::response($this->listResponse([])),
            '*/zones/zone-123/custom_hostnames' => Http::failedConnection('cURL error 28: Operation timed out'),
        ]);

        $status = (new CloudflareDomainProvisioner)->requestTlsProvisioning($this->domain(), 'key-1');

        $this->assertSame(ProvisioningStatus::Unavailable, $status);
    }

    public function test_lookup_connection_failure_is_unavailable(): void
    {
        Http::fake([
            '*/zones/zone-123/custom_hostnames?*' => Http::failedConnection('cURL error 6: Could not resolve host'),
        ]);

        $status = (new CloudflareDomainProvisioner)->requestTlsProvisioning($this->domain(), 'key-1');

        $this->assertSame(ProvisioningStatus::Unavailable, $status);
    }

    // --- Status ----------------------------------------------------------------

    public function test_active_hostname_and_active_ssl_is_ready(): void
    {
        $this->assertStatusFor('active', 'active', ProvisioningStatus::Ready);
    }

    public function test_active_hostname_and_pending_ssl_is_not_ready(): void
    {
        $this->assertStatusFor('active', 'pending_validation', ProvisioningStatus::Pending);
    }

    public function test_pending_hostname_and_active_ssl_is_not_ready(): void
    {
        $this->assertStatusFor('pending', 'active', ProvisioningStatus::Pending);
    }

    public function test_pending_hostname_and_pending_ssl_is_not_ready(): void
    {
        $this->assertStatusFor('pending', 'pending_deployment', ProvisioningStatus::Pending);
    }

    public function test_terminal_provider_failure_is_failed(): void
    {
        $this->assertStatusFor('blocked', 'active', ProvisioningStatus::Failed);
    }

    public function test_unrecognized_ssl_status_is_failed_not_stuck_pending(): void
    {
        $this->assertStatusFor('active', 'expired', ProvisioningStatus::Failed);
    }

    public function test_status_check_provider_unavailable(): void
    {
        Http::fake(['*/zones/zone-123/custom_hostnames?*' => Http::response(['error' => 'boom'], 503)]);

        $status = (new CloudflareDomainProvisioner)->checkTlsStatus($this->domain());

        $this->assertSame(ProvisioningStatus::Unavailable, $status);
    }

    public function test_status_check_zero_matches_is_unavailable(): void
    {
        Http::fake(['*/zones/zone-123/custom_hostnames?*' => Http::response($this->listResponse([]))]);

        $status = (new CloudflareDomainProvisioner)->checkTlsStatus($this->domain());

        $this->assertSame(ProvisioningStatus::Unavailable, $status);
    }

    public function test_status_check_ambiguous_matches_is_failed(): void
    {
        Http::fake(['*/zones/zone-123/custom_hostnames?*' => Http::response($this->listResponse([
            $this->hostnameObject('church.example.org', 'active', 'active', 'ch-1'),
            $this->hostnameObject('church.example.org', 'active', 'active', 'ch-2'),
        ]))]);

        $status = (new CloudflareDomainProvisioner)->checkTlsStatus($this->domain());

        $this->assertSame(ProvisioningStatus::Failed, $status);
    }

    private function assertStatusFor(string $hostnameStatus, string $sslStatus, ProvisioningStatus $expected): void
    {
        Http::fake(['*/zones/zone-123/custom_hostnames?*' => Http::response($this->listResponse([
            $this->hostnameObject('church.example.org', $hostnameStatus, $sslStatus),
        ]))]);

        $status = (new CloudflareDomainProvisioner)->checkTlsStatus($this->domain());

        $this->assertSame($expected, $status);
    }

    // --- Deactivate --------------------------------------------------------------

    public function test_deactivate_deletes_using_id_obtained_from_lookup(): void
    {
        Http::fake([
            '*/zones/zone-123/custom_hostnames?*' => Http::response($this->listResponse([
                $this->hostnameObject('church.example.org', 'active', 'active', 'ch-to-delete'),
            ])),
            '*/zones/zone-123/custom_hostnames/ch-to-delete' => Http::response(['success' => true]),
        ]);

        (new CloudflareDomainProvisioner)->deactivate($this->domain());

        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && str_ends_with($request->url(), '/zones/zone-123/custom_hostnames/ch-to-delete'));
    }

    public function test_deactivate_is_idempotent_when_already_absent(): void
    {
        Http::fake(['*/zones/zone-123/custom_hostnames?*' => Http::response($this->listResponse([]))]);

        (new CloudflareDomainProvisioner)->deactivate($this->domain());

        Http::assertNotSent(fn ($request) => $request->method() === 'DELETE');
    }

    public function test_deactivate_fails_closed_on_ambiguous_matches(): void
    {
        Http::fake(['*/zones/zone-123/custom_hostnames?*' => Http::response($this->listResponse([
            $this->hostnameObject('church.example.org', 'active', 'active', 'ch-1'),
            $this->hostnameObject('church.example.org', 'active', 'active', 'ch-2'),
        ]))]);

        $this->expectException(ProviderIntegrationException::class);

        (new CloudflareDomainProvisioner)->deactivate($this->domain());
    }

    public function test_deactivate_throws_on_delete_failure(): void
    {
        Http::fake([
            '*/zones/zone-123/custom_hostnames?*' => Http::response($this->listResponse([
                $this->hostnameObject('church.example.org', 'active', 'active', 'ch-1'),
            ])),
            '*/zones/zone-123/custom_hostnames/ch-1' => Http::response(['error' => 'boom'], 500),
        ]);

        $this->expectException(ProviderIntegrationException::class);

        (new CloudflareDomainProvisioner)->deactivate($this->domain());
    }

    public function test_deactivate_throws_on_provider_or_network_failure_during_lookup(): void
    {
        Http::fake(['*/zones/zone-123/custom_hostnames?*' => Http::failedConnection('cURL error 6: Could not resolve host')]);

        $this->expectException(ProviderIntegrationException::class);

        (new CloudflareDomainProvisioner)->deactivate($this->domain());
    }

    // --- Missing configuration -----------------------------------------------------

    public function test_missing_configuration_fails_closed_for_request_and_check(): void
    {
        config()->set('cloudflare.provisioner.zone_id', '');

        $this->assertSame(ProvisioningStatus::Unavailable, (new CloudflareDomainProvisioner)->requestTlsProvisioning($this->domain(), 'key-1'));
        $this->assertSame(ProvisioningStatus::Unavailable, (new CloudflareDomainProvisioner)->checkTlsStatus($this->domain()));
    }

    public function test_missing_configuration_raises_for_deactivate(): void
    {
        config()->set('cloudflare.provisioner.api_token', '');

        $this->expectException(ProvisioningConfigurationException::class);

        (new CloudflareDomainProvisioner)->deactivate($this->domain());
    }

    public function test_config_file_has_no_custom_origin_configuration_at_all(): void
    {
        // K-DOMAIN-001G-A §5/§6: the V1 architecture never configures a
        // per-hostname Custom Origin, so the config file must not declare
        // custom_origin_server / custom_origin_sni as a canonical setting.
        $source = file_get_contents(config_path('cloudflare.php'));
        $codeWithoutComments = preg_replace('#//.*#', '', $source);

        $this->assertStringNotContainsString('custom_origin_server', $codeWithoutComments);
        $this->assertStringNotContainsString('custom_origin_sni', $codeWithoutComments);
        $this->assertStringNotContainsString('origin.staging.keryon.app', $codeWithoutComments);
    }

    // --- Security --------------------------------------------------------------------

    public function test_api_token_never_appears_in_thrown_exception_messages(): void
    {
        Http::fake(['*/zones/zone-123/custom_hostnames?*' => Http::response($this->listResponse([
            $this->hostnameObject('church.example.org', 'active', 'active', 'ch-1'),
            $this->hostnameObject('church.example.org', 'active', 'active', 'ch-2'),
        ]))]);

        try {
            (new CloudflareDomainProvisioner)->deactivate($this->domain());
            $this->fail('Expected ProviderIntegrationException.');
        } catch (ProviderIntegrationException $exception) {
            $this->assertStringNotContainsString('secret-token-value', $exception->getMessage());
        }
    }

    public function test_authorization_header_is_bearer_token_and_not_otherwise_exposed(): void
    {
        Http::fake([
            '*/zones/zone-123/custom_hostnames?*' => Http::response($this->listResponse([])),
            '*/zones/zone-123/custom_hostnames' => Http::response([
                'success' => true,
                'result' => $this->hostnameObject('church.example.org', 'active', 'active'),
            ]),
        ]);

        (new CloudflareDomainProvisioner)->requestTlsProvisioning($this->domain(), 'key-1');

        Http::assertSent(function ($request) {
            $auth = $request->header('Authorization')[0] ?? '';

            return $auth === 'Bearer secret-token-value';
        });
    }
}
