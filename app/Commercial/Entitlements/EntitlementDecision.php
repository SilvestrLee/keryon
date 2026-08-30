<?php

namespace App\Commercial\Entitlements;

use App\Enums\EntitlementDecisionReason;
use App\Enums\EntitlementKey;
use Carbon\CarbonImmutable;

final readonly class EntitlementDecision
{
    public function __construct(
        public bool $allowed,
        public EntitlementKey $entitlement,
        public ?EntitlementValue $value,
        public string $source,
        public ?int $planVersionId,
        public EntitlementDecisionReason $reason,
        public ?CarbonImmutable $effectiveUntil,
    ) {}

    public function allows(): bool
    {
        return $this->allowed;
    }
}
