<?php

namespace App\Billing;

use App\Commercial\Pricing\PricingService;
use App\Enums\BillingAccountStatus;
use App\Enums\CommercialAuditEventType;
use App\Enums\SubscriptionItemStatus;
use App\Enums\SubscriptionStatus;
use App\Models\BillingAccount;
use App\Models\Church;
use App\Models\CommercialAuditEvent;
use App\Models\PlanVersion;
use App\Models\Price;
use App\Models\PricingMarket;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    public const DEFAULT_TRIAL_DAYS = 21;

    public function __construct(private readonly PricingService $pricing) {}

    public function startTrial(
        Church $church,
        BillingAccount $payer,
        PricingMarket $market,
        PlanVersion $version,
        Price $price,
        ?int $actorUserId,
        string $origin,
        ?DateTimeInterface $at = null,
        int $days = self::DEFAULT_TRIAL_DAYS,
    ): Subscription {
        if ($days <= 0) {
            throw new DomainException('Trial duration must be positive.');
        }
        $startedAt = CarbonImmutable::instance($at ?? now());

        return $this->provision($church, $payer, $market, $version, $price, SubscriptionStatus::TRIALING, $actorUserId, $origin, $startedAt, $startedAt->addDays($days));
    }

    public function activate(
        Church $church,
        BillingAccount $payer,
        PricingMarket $market,
        PlanVersion $version,
        Price $price,
        ?int $actorUserId,
        string $origin,
        ?DateTimeInterface $at = null,
    ): Subscription {
        $startedAt = CarbonImmutable::instance($at ?? now());

        return $this->provision($church, $payer, $market, $version, $price, SubscriptionStatus::ACTIVE, $actorUserId, $origin, $startedAt, null);
    }

    public function cancel(Subscription $subscription, ?int $actorUserId, string $origin, ?DateTimeInterface $at = null): Subscription
    {
        return $this->finish($subscription, SubscriptionStatus::CANCELLED, CommercialAuditEventType::SUBSCRIPTION_CANCELLED, $actorUserId, $origin, $at);
    }

    public function activateTrial(Subscription $subscription, ?int $actorUserId, string $origin, ?DateTimeInterface $at = null): Subscription
    {
        $this->assertOrigin($origin);
        $activatedAt = CarbonImmutable::instance($at ?? now());

        return DB::transaction(function () use ($subscription, $actorUserId, $origin, $activatedAt): Subscription {
            $locked = Subscription::query()->lockForUpdate()->findOrFail($subscription->id);
            $church = Church::query()->lockForUpdate()->findOrFail($locked->church_id);
            if ($locked->status !== SubscriptionStatus::TRIALING
                || $locked->trial_ends_at === null
                || $locked->trial_ends_at->lte($activatedAt)
                || $church->current_subscription_id !== $locked->id) {
                throw new DomainException('Only the current unexpired trial may be activated.');
            }
            $locked->forceFill([
                'status' => SubscriptionStatus::ACTIVE,
                'current_period_start' => $activatedAt,
            ])->save();
            $this->audit(CommercialAuditEventType::SUBSCRIPTION_ACTIVATED, $locked, $actorUserId, ['status' => 'trialing'], ['status' => 'active', 'origin' => $origin]);

            return $locked->fresh(['item', 'billingAccount', 'pricingMarket']);
        });
    }

    public function end(Subscription $subscription, ?int $actorUserId, string $origin, ?DateTimeInterface $at = null): Subscription
    {
        return $this->finish($subscription, SubscriptionStatus::ENDED, CommercialAuditEventType::SUBSCRIPTION_ENDED, $actorUserId, $origin, $at);
    }

    public function changePayer(Subscription $subscription, BillingAccount $payer, ?int $actorUserId, string $origin): Subscription
    {
        $this->assertOrigin($origin);

        return DB::transaction(function () use ($subscription, $payer, $actorUserId, $origin): Subscription {
            $locked = Subscription::query()->lockForUpdate()->findOrFail($subscription->id);
            $newPayer = BillingAccount::query()->lockForUpdate()->findOrFail($payer->id);
            if ($newPayer->status !== BillingAccountStatus::ACTIVE) {
                throw new DomainException('An inactive BillingAccount cannot become a payer.');
            }
            $previous = $locked->billing_account_id;
            $locked->forceFill(['billing_account_id' => $newPayer->id])->save();
            $this->audit(CommercialAuditEventType::SUBSCRIPTION_PAYER_CHANGED, $locked, $actorUserId, ['billing_account_id' => $previous], ['billing_account_id' => $newPayer->id, 'origin' => $origin]);

            return $locked->fresh(['item', 'billingAccount', 'pricingMarket']);
        });
    }

    private function provision(
        Church $church,
        BillingAccount $payer,
        PricingMarket $market,
        PlanVersion $version,
        Price $price,
        SubscriptionStatus $status,
        ?int $actorUserId,
        string $origin,
        CarbonImmutable $startedAt,
        ?CarbonImmutable $trialEndsAt,
    ): Subscription {
        $this->assertOrigin($origin);

        return DB::transaction(function () use ($church, $payer, $market, $version, $price, $status, $actorUserId, $origin, $startedAt, $trialEndsAt): Subscription {
            $lockedChurch = Church::query()->lockForUpdate()->findOrFail($church->id);
            $lockedPayer = BillingAccount::query()->lockForUpdate()->findOrFail($payer->id);
            if ($lockedChurch->current_subscription_id !== null) {
                throw new DomainException('The Church already has an effective base Subscription.');
            }
            if ($lockedPayer->status !== BillingAccountStatus::ACTIVE) {
                throw new DomainException('An inactive BillingAccount cannot become a payer.');
            }
            $decision = $this->pricing->quote($version, $market, $price->billing_interval, $startedAt);
            if (! $decision->resolved || $decision->priceId !== $price->id || $price->plan_version_id !== $version->id) {
                throw new DomainException('Subscription Price, PlanVersion, and PricingMarket must resolve as one governed commercial definition.');
            }

            $subscription = Subscription::query()->create([
                'church_id' => $lockedChurch->id,
                'billing_account_id' => $lockedPayer->id,
                'pricing_market_id' => $market->id,
                'status' => $status,
                'effective_slot' => 1,
                'started_at' => $startedAt,
                'trial_started_at' => $status === SubscriptionStatus::TRIALING ? $startedAt : null,
                'trial_ends_at' => $trialEndsAt,
                'cancel_at_period_end' => false,
            ]);
            $subscription->item()->create([
                'plan_version_id' => $version->id,
                'price_id' => $price->id,
                'quantity' => 1,
                'status' => SubscriptionItemStatus::ACTIVE,
                'effective_from' => $startedAt,
            ]);
            $lockedChurch->forceFill(['current_subscription_id' => $subscription->id])->save();
            $event = $status === SubscriptionStatus::TRIALING ? CommercialAuditEventType::TRIAL_STARTED : CommercialAuditEventType::SUBSCRIPTION_ACTIVATED;
            $this->audit($event, $subscription, $actorUserId, null, [
                'status' => $status->value, 'billing_account_id' => $lockedPayer->id,
                'pricing_market_id' => $market->id, 'plan_version_id' => $version->id,
                'price_id' => $price->id, 'origin' => $origin, 'trial_ends_at' => $trialEndsAt?->toIso8601String(),
            ]);

            return $subscription->fresh(['item.planVersion', 'item.price', 'billingAccount', 'pricingMarket']);
        });
    }

    private function finish(Subscription $subscription, SubscriptionStatus $status, CommercialAuditEventType $event, ?int $actor, string $origin, ?DateTimeInterface $at): Subscription
    {
        $this->assertOrigin($origin);
        $endedAt = CarbonImmutable::instance($at ?? now());

        return DB::transaction(function () use ($subscription, $status, $event, $actor, $origin, $endedAt): Subscription {
            $locked = Subscription::query()->lockForUpdate()->findOrFail($subscription->id);
            $church = Church::query()->lockForUpdate()->findOrFail($locked->church_id);
            if (! $locked->status->isEffective() || $church->current_subscription_id !== $locked->id) {
                throw new DomainException('Only the current effective Subscription may be ended.');
            }
            $previous = $locked->status->value;
            $locked->forceFill([
                'status' => $status,
                'effective_slot' => null,
                'cancel_at_period_end' => false,
                'cancelled_at' => $status === SubscriptionStatus::CANCELLED ? $endedAt : $locked->cancelled_at,
                'ended_at' => $endedAt,
            ])->save();
            $locked->item()->update(['status' => SubscriptionItemStatus::ENDED, 'effective_until' => $endedAt]);
            $church->forceFill(['current_subscription_id' => null])->save();
            $this->audit($event, $locked, $actor, ['status' => $previous], ['status' => $status->value, 'origin' => $origin]);

            return $locked->fresh(['item', 'billingAccount', 'pricingMarket']);
        });
    }

    private function audit(CommercialAuditEventType $event, Subscription $subscription, ?int $actor, ?array $previous, ?array $new): void
    {
        CommercialAuditEvent::query()->create([
            'event_type' => $event,
            'church_id' => $subscription->church_id,
            'billing_account_id' => $subscription->billing_account_id,
            'subscription_id' => $subscription->id,
            'actor_user_id' => $actor,
            'previous_state' => $previous,
            'new_state' => $new,
            'occurred_at' => now(),
        ]);
    }

    private function assertOrigin(string $origin): void
    {
        if ($origin === '') {
            throw new DomainException('Subscription lifecycle actions require an explicit origin.');
        }
    }
}
