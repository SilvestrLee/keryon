<?php

namespace App\Domain\Dns;

final class UnavailableDnsResolver implements DnsResolver
{
    public function txt(string $hostname): DnsLookupResult
    {
        return DnsLookupResult::unavailable();
    }

    public function cname(string $hostname): DnsLookupResult
    {
        return DnsLookupResult::unavailable();
    }

    public function addresses(string $hostname): DnsAddressResult
    {
        return new DnsAddressResult(DnsLookupStatus::Unavailable);
    }
}
