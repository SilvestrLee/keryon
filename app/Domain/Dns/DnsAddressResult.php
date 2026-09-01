<?php

namespace App\Domain\Dns;

final readonly class DnsAddressResult
{
    /** @param list<string> $ipv4 @param list<string> $ipv6 */
    public function __construct(
        public DnsLookupStatus $status,
        public array $ipv4 = [],
        public array $ipv6 = [],
    ) {}
}
