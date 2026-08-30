<?php

namespace App\Commercial\Entitlements;

use App\Enums\EntitlementDecisionReason;
use App\Enums\EntitlementKey;
use App\Enums\EntitlementValueType;
use App\Models\Church;
use Carbon\CarbonImmutable;

class EntitlementResolver
{
    public function __construct(private readonly ProductEntitlementSource $source) {}

    public function decision(Church $church, EntitlementKey $key): EntitlementDecision
    {
        $version = $this->source->planVersionFor($church);

        if ($version === null) {
            return $this->deny($key, EntitlementDecisionReason::NO_PRODUCT_ASSIGNMENT);
        }

        if (! $version->status->isRuntimeEligible()) {
            return $this->deny($key, EntitlementDecisionReason::PLAN_VERSION_INACTIVE, $version->id);
        }

        $definition = $version->entitlements()->where('entitlement_key', $key->value)->first();
        if ($definition === null) {
            return $this->deny($key, EntitlementDecisionReason::ENTITLEMENT_MISSING, $version->id);
        }

        $value = $definition->value();
        $allowed = match ($value->type) {
            EntitlementValueType::BOOLEAN => $value->booleanValue(),
            EntitlementValueType::INTEGER => $value->integerValue() > 0,
        };
        $reason = match (true) {
            ! $allowed => EntitlementDecisionReason::ENTITLEMENT_DISABLED,
            $value->type === EntitlementValueType::INTEGER => EntitlementDecisionReason::LIMIT_AVAILABLE,
            default => EntitlementDecisionReason::ALLOWED,
        };

        return new EntitlementDecision(
            allowed: $allowed,
            entitlement: $key,
            value: $value,
            source: $this->source->identifier(),
            planVersionId: $version->id,
            reason: $reason,
            effectiveUntil: $version->effective_until === null
                ? null
                : CarbonImmutable::instance($version->effective_until),
        );
    }

    public function allows(Church $church, EntitlementKey $key): bool
    {
        return $this->decision($church, $key)->allows();
    }

    private function deny(EntitlementKey $key, EntitlementDecisionReason $reason, ?int $versionId = null): EntitlementDecision
    {
        return new EntitlementDecision(
            allowed: false,
            entitlement: $key,
            value: null,
            source: $this->source->identifier(),
            planVersionId: $versionId,
            reason: $reason,
            effectiveUntil: null,
        );
    }
}
