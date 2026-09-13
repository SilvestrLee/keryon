<?php

namespace App\Domain;

use App\Enums\DomainFailureCode;

/**
 * The pure outcome of one sub-check (ownership, routing, or TLS/provider
 * health) — carries no side effects. See ChurchDomainHealthCycle, which
 * combines several of these into exactly one lifecycle mutation per cycle.
 */
final readonly class DomainCheckResult
{
    private function __construct(
        public HealthOutcome $outcome,
        public ?DomainFailureCode $failureCode = null,
    ) {}

    public static function healthy(): self
    {
        return new self(HealthOutcome::Healthy);
    }

    public static function unhealthy(DomainFailureCode $code): self
    {
        return new self(HealthOutcome::ConfirmedUnhealthy, $code);
    }

    public static function indeterminate(?DomainFailureCode $code = null): self
    {
        return new self(HealthOutcome::Indeterminate, $code);
    }
}
