<?php

namespace App\Billing\ValueObjects;

use App\Enums\BillingInterval;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use DomainException;

final readonly class ServicePeriod
{
    public CarbonImmutable $start;

    public CarbonImmutable $end;

    public function __construct(DateTimeInterface $start, DateTimeInterface $end)
    {
        $this->start = CarbonImmutable::instance($start);
        $this->end = CarbonImmutable::instance($end);
        if ($this->end->lte($this->start)) {
            throw new DomainException('A service period must be non-empty.');
        }
    }

    public static function from(DateTimeInterface $start, BillingInterval $interval): self
    {
        $from = CarbonImmutable::instance($start);
        $end = $interval === BillingInterval::MONTHLY ? $from->addMonthNoOverflow() : $from->addYearNoOverflow();

        return new self($from, $end);
    }

    public function key(): string
    {
        return $this->start->format('YmdHis').'-'.$this->end->format('YmdHis');
    }
}
