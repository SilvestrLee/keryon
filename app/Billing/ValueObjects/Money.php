<?php

namespace App\Billing\ValueObjects;

use DomainException;

final readonly class Money
{
    public function __construct(public int $amountMinor, public string $currency)
    {
        if ($amountMinor < 0) {
            throw new DomainException('Money cannot be negative.');
        }
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new DomainException('Money requires an uppercase ISO currency code.');
        }
    }

    public function add(self $other): self
    {
        if ($other->currency !== $this->currency) {
            throw new DomainException('Currencies must match.');
        }

        return new self($this->amountMinor + $other->amountMinor, $this->currency);
    }
}
