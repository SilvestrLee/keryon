<?php

namespace App\Domain\Dns;

use LogicException;

final class FakeDnsResolver implements DnsResolver
{
    /** @var array<string,DnsLookupResult> */
    private array $txt = [];

    /** @var array<string,DnsLookupResult> */
    private array $cnames = [];

    /** @var array<string,DnsAddressResult> */
    private array $addresses = [];

    public function __construct()
    {
        if (app()->environment('production')) {
            throw new LogicException('The fake DNS resolver cannot run in production.');
        }
    }

    /** @param list<string> $values */
    public function setTxt(string $hostname, array $values): self
    {
        $this->txt[strtolower($hostname)] = DnsLookupResult::found($values);

        return $this;
    }

    /** @param list<string> $values */
    public function setCname(string $hostname, array $values): self
    {
        $this->cnames[strtolower($hostname)] = DnsLookupResult::found($values);

        return $this;
    }

    /** @param list<string> $ipv4 @param list<string> $ipv6 */
    public function setAddresses(string $hostname, array $ipv4, array $ipv6 = []): self
    {
        $this->addresses[strtolower($hostname)] = new DnsAddressResult(DnsLookupStatus::Found, $ipv4, $ipv6);

        return $this;
    }

    public function setTxtResult(string $hostname, DnsLookupResult $result): self
    {
        $this->txt[strtolower($hostname)] = $result;

        return $this;
    }

    public function txt(string $hostname): DnsLookupResult
    {
        return $this->txt[strtolower($hostname)] ?? DnsLookupResult::notFound();
    }

    public function cname(string $hostname): DnsLookupResult
    {
        return $this->cnames[strtolower($hostname)] ?? DnsLookupResult::notFound();
    }

    public function addresses(string $hostname): DnsAddressResult
    {
        return $this->addresses[strtolower($hostname)] ?? new DnsAddressResult(DnsLookupStatus::NotFound);
    }
}
