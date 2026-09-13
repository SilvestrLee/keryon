<?php

namespace App\Domain;

use App\Domain\Dns\DnsLookupStatus;
use App\Domain\Dns\DnsResolver;
use App\Enums\DomainFailureCode;
use App\Models\ChurchDomain;

final readonly class VerifyChurchDomainRouting
{
    public function __construct(private DnsResolver $dns, private ChurchDomainLifecycle $lifecycle) {}

    /**
     * Pure evaluation — no lifecycle mutation. Reused by both the initial
     * verification flow (execute()) and the ongoing health cycle
     * (ChurchDomainHealthCycle) so the CNAME/address-matching rule lives in
     * one place.
     */
    public function check(ChurchDomain $domain): DomainCheckResult
    {
        $target = strtolower(rtrim((string) config('public-website.custom_domains.dns_ingress_target'), '.'));
        $cname = $this->dns->cname($domain->normalized_hostname);

        if ($target !== '' && $cname->status === DnsLookupStatus::Found
            && collect($cname->values)->contains(fn (string $value): bool => strtolower(rtrim($value, '.')) === $target)) {
            return DomainCheckResult::healthy();
        }

        $addresses = $this->dns->addresses($domain->normalized_hostname);
        $expectedIpv4 = config('public-website.custom_domains.apex_ipv4', []);
        $expectedIpv6 = config('public-website.custom_domains.apex_ipv6', []);
        $addressMatches = $addresses->status === DnsLookupStatus::Found
            && ((array_intersect($addresses->ipv4, $expectedIpv4) !== []) || (array_intersect($addresses->ipv6, $expectedIpv6) !== []));

        if ($addressMatches) {
            return DomainCheckResult::healthy();
        }

        $status = $cname->status === DnsLookupStatus::Found ? DnsLookupStatus::Found : $cname->status;

        return match ($status) {
            DnsLookupStatus::Timeout => DomainCheckResult::indeterminate(DomainFailureCode::DnsLookupTimeout),
            DnsLookupStatus::Unavailable => DomainCheckResult::indeterminate(DomainFailureCode::ProviderUnavailable),
            DnsLookupStatus::NotFound => DomainCheckResult::unhealthy(DomainFailureCode::DnsNotConfigured),
            DnsLookupStatus::Found => DomainCheckResult::unhealthy(DomainFailureCode::DnsMismatch),
        };
    }

    public function execute(ChurchDomain $domain, ?string $correlationId = null): bool
    {
        $result = $this->check($domain);

        if ($result->outcome === HealthOutcome::Healthy) {
            $this->lifecycle->routingVerified($domain, correlationId: $correlationId);

            return true;
        }

        $this->lifecycle->verificationFailed($domain, $result->failureCode ?? DomainFailureCode::DnsMismatch, $correlationId);

        return false;
    }
}
