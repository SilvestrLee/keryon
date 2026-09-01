<?php

namespace App\Domain\Dns;

final readonly class DnsLookupResult
{
    /** @param list<string> $values */
    public function __construct(public DnsLookupStatus $status, public array $values = []) {}

    /** @param list<string> $values */
    public static function found(array $values): self
    {
        return new self(DnsLookupStatus::Found, $values);
    }

    public static function notFound(): self
    {
        return new self(DnsLookupStatus::NotFound);
    }

    public static function timeout(): self
    {
        return new self(DnsLookupStatus::Timeout);
    }

    public static function unavailable(): self
    {
        return new self(DnsLookupStatus::Unavailable);
    }
}
