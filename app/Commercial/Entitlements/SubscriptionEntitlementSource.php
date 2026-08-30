<?php

namespace App\Commercial\Entitlements;

use App\Enums\SubscriptionItemStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Church;
use App\Models\PlanVersion;
use App\Models\Subscription;

class SubscriptionEntitlementSource implements ProductEntitlementSource
{
    private string $resolvedSource = 'subscription';

    public function __construct(private readonly CurrentProductEntitlementSource $fallback) {}

    public function planVersionFor(Church $church): ?PlanVersion
    {
        $subscription = Subscription::query()
            ->with('item.planVersion')
            ->where('id', $church->current_subscription_id)
            ->where('church_id', $church->id)
            ->first();

        if ($subscription !== null && $this->isEligible($subscription)) {
            $this->resolvedSource = 'subscription';

            return $subscription->item?->planVersion;
        }

        if ($church->subscriptions()->exists()) {
            $this->resolvedSource = 'subscription_inactive';

            return null;
        }

        if (! config('commercial.transitional_subscription_fallback', true)) {
            $this->resolvedSource = 'subscription_missing';

            return null;
        }

        $this->resolvedSource = 'transitional_current_product';

        return $this->fallback->planVersionFor($church);
    }

    public function identifier(): string
    {
        return $this->resolvedSource;
    }

    private function isEligible(Subscription $subscription): bool
    {
        if (! $subscription->status->isEffective()
            || $subscription->item?->status !== SubscriptionItemStatus::ACTIVE) {
            return false;
        }

        return $subscription->status !== SubscriptionStatus::TRIALING
            || ($subscription->trial_ends_at !== null && $subscription->trial_ends_at->isFuture());
    }
}
