<?php

namespace App\Domain\Dns;

interface DnsResolver
{
    public function txt(string $hostname): DnsLookupResult;

    public function cname(string $hostname): DnsLookupResult;

    public function addresses(string $hostname): DnsAddressResult;
}
