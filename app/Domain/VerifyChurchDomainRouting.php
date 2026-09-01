<?php

namespace App\Domain;

use App\Domain\Dns\DnsLookupStatus;
use App\Domain\Dns\DnsResolver;
use App\Enums\DomainFailureCode;
use App\Models\ChurchDomain;

final readonly class VerifyChurchDomainRouting
{
    public function __construct(private DnsResolver $dns, private ChurchDomainLifecycle $lifecycle) {}

    public function execute(ChurchDomain $domain, ?string $correlationId = null): bool
    {
        $target = strtolower(rtrim((string) config('public-website.custom_domains.dns_ingress_target'), '.'));
        $cname = $this->dns->cname($domain->normalized_hostname);

        if ($target !== '' && $cname->status === DnsLookupStatus::Found
            && collect($cname->values)->contains(fn (string $value): bool => strtolower(rtrim($value, '.')) === $target)) {
            $this->lifecycle->routingVerified($domain, correlationId: $correlationId);

            return true;
        }

        $addresses = $this->dns->addresses($domain->normalized_hostname);
        $expectedIpv4 = config('public-website.custom_domains.apex_ipv4', []);
        $expectedIpv6 = config('public-website.custom_domains.apex_ipv6', []);
        $addressMatches = $addresses->status === DnsLookupStatus::Found
            && ((array_intersect($addresses->ipv4, $expectedIpv4) !== []) || (array_intersect($addresses->ipv6, $expectedIpv6) !== []));

        if ($addressMatches) {
            $this->lifecycle->routingVerified($domain, correlationId: $correlationId);

            return true;
        }

        $status = $cname->status === DnsLookupStatus::Found ? DnsLookupStatus::Found : $cname->status;
        $this->lifecycle->verificationFailed($domain, match ($status) {
            DnsLookupStatus::Timeout => DomainFailureCode::DnsLookupTimeout,
            DnsLookupStatus::Unavailable => DomainFailureCode::ProviderUnavailable,
            DnsLookupStatus::NotFound => DomainFailureCode::DnsNotConfigured,
            default => DomainFailureCode::DnsMismatch,
        }, $correlationId);

        return false;
    }
}
