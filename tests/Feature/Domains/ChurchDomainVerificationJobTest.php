<?php

namespace Tests\Feature\Domains;

use App\Domain\Dns\DnsResolver;
use App\Domain\Dns\FakeDnsResolver;
use App\Domain\Provisioning\DomainProvisioner;
use App\Domain\Provisioning\FakeDomainProvisioner;
use App\Domain\Provisioning\ProvisioningStatus;
use App\Domain\RequestChurchCustomDomain;
use App\Enums\ChurchRole;
use App\Enums\DomainFailureCode;
use App\Enums\DomainStatus;
use App\Enums\DomainTlsStatus;
use App\Jobs\VerifyChurchDomain;
use App\Models\Church;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ChurchDomainVerificationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_uses_domain_id_only_and_advances_fake_provider_lifecycle_idempotently(): void
    {
        config()->set('public-website.custom_domains.dns_ingress_target', 'ingress.keryon.app');
        $church = Church::create(['name' => 'Job Church', 'slug' => 'job-church', 'activated_at' => now()]);
        $user = User::factory()->forChurch($church, [ChurchRole::ADMINISTRATOR], primary: true)->create();
        $this->actingAs($user);
        $claim = app(RequestChurchCustomDomain::class)->execute($church, 'www.job-church.org');

        $dns = (new FakeDnsResolver)
            ->setTxt($claim->verificationHostname, [$claim->verificationToken])
            ->setCname($claim->domain->normalized_hostname, ['ingress.keryon.app']);
        $provisioner = (new FakeDomainProvisioner)->advanceTo(ProvisioningStatus::Ready);
        $this->app->instance(DnsResolver::class, $dns);
        $this->app->instance(DomainProvisioner::class, $provisioner);

        $job = new VerifyChurchDomain($claim->domain->id, '37ae3a12-4b33-4d5f-93ef-2742d061bb74');
        $this->app->call([$job, 'handle']);
        $this->app->call([$job, 'handle']);

        $domain = $claim->domain->fresh();
        $this->assertSame(DomainStatus::Active, $domain->status);
        $this->assertSame(DomainTlsStatus::Ready, $domain->tls_status);
        $this->assertNotNull($domain->ownership_verified_at);
        $this->assertNotNull($domain->routing_verified_at);
        $this->assertSame(1, $provisioner->requestCount());
        $this->assertSame(7, DB::table('church_domain_events')->where('church_domain_id', $domain->id)->count());
        $this->assertStringNotContainsString($claim->verificationToken, serialize($job));
    }

    public function test_unavailable_production_seams_fail_closed_without_false_activation(): void
    {
        config()->set('public-website.custom_domains.dns_ingress_target', 'ingress.keryon.app');
        $church = Church::create(['name' => 'Closed Church', 'slug' => 'closed-church']);
        $user = User::factory()->forChurch($church, [ChurchRole::ADMINISTRATOR], primary: true)->create();
        $this->actingAs($user);
        $claim = app(RequestChurchCustomDomain::class)->execute($church, 'closed-church.org');

        $this->app->call([(new VerifyChurchDomain($claim->domain->id)), 'handle']);

        $domain = $claim->domain->fresh();
        $this->assertSame(DomainStatus::PendingVerification, $domain->status);
        $this->assertSame(DomainTlsStatus::NotStarted, $domain->tls_status);
        $this->assertSame(DomainFailureCode::ProviderUnavailable->value, $domain->failure_code);
        $this->assertNull($domain->activated_at);
    }

    public function test_provider_unavailable_during_initial_tls_request_remains_provisioning_not_terminally_failed(): void
    {
        // K-DOMAIN-001E §17 — a Cloudflare management API outage during the
        // initial requestTlsProvisioning() call must not be treated as a
        // certificate failure. It must remain retriable (PollChurchDomainTls
        // picks it up later), exactly like a later poll's Unavailable result.
        config()->set('public-website.custom_domains.dns_ingress_target', 'ingress.keryon.app');
        $church = Church::create(['name' => 'Retriable Church', 'slug' => 'retriable-church', 'activated_at' => now()]);
        $user = User::factory()->forChurch($church, [ChurchRole::ADMINISTRATOR], primary: true)->create();
        $this->actingAs($user);
        $claim = app(RequestChurchCustomDomain::class)->execute($church, 'retriable-church.org');

        $dns = (new FakeDnsResolver)
            ->setTxt($claim->verificationHostname, [$claim->verificationToken])
            ->setCname($claim->domain->normalized_hostname, ['ingress.keryon.app']);
        $provisioner = (new FakeDomainProvisioner)->advanceTo(ProvisioningStatus::Unavailable);
        $this->app->instance(DnsResolver::class, $dns);
        $this->app->instance(DomainProvisioner::class, $provisioner);

        $this->app->call([new VerifyChurchDomain($claim->domain->id), 'handle']);

        $domain = $claim->domain->fresh();
        $this->assertSame(DomainTlsStatus::Provisioning, $domain->tls_status);
        $this->assertNotSame(DomainFailureCode::CertificateFailed->value, $domain->failure_code);
        $this->assertNull($domain->activated_at);
    }
}
