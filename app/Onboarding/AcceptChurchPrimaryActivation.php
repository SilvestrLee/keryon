<?php

namespace App\Onboarding;

use App\Billing\BillingAccountService;
use App\Billing\SubscriptionService;
use App\Commercial\Pricing\PricingService;
use App\Enums\BillingAccountOwnerType;
use App\Enums\BillingAccountStatus;
use App\Enums\ChurchActivationStatus;
use App\Enums\ChurchRole;
use App\Models\BillingAccount;
use App\Models\BillingProfile;
use App\Models\Church;
use App\Models\ChurchActivation;
use App\Models\ChurchMembership;
use App\Models\Organization;
use App\Models\OrganizationUnit;
use App\Models\PlanVersion;
use App\Models\Price;
use App\Models\PricingMarket;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

class AcceptChurchPrimaryActivation
{
    public function __construct(
        private readonly BillingAccountService $accounts,
        private readonly SubscriptionService $subscriptions,
        private readonly PricingService $pricing,
    ) {}

    public function execute(string $rawToken, User $user, string $termsVersion, string $privacyVersion, string $idempotencyKey): AcceptChurchPrimaryActivationResult
    {
        if ($rawToken === '' || $termsVersion === '' || $privacyVersion === '' || $idempotencyKey === '') {
            throw new DomainException('Activation token, legal versions, and idempotency are required.');
        }
        $tokenHash = ChurchActivationTokenService::hash($rawToken);

        return DB::transaction(function () use ($tokenHash, $user, $termsVersion, $privacyVersion, $idempotencyKey): AcceptChurchPrimaryActivationResult {
            $activation = ChurchActivation::query()->where('token_hash', $tokenHash)->lockForUpdate()->first();
            if (! $activation) {
                throw new DomainException('The Church activation token is invalid.');
            }
            if ($activation->status === ChurchActivationStatus::ACCEPTED) {
                if ($activation->legal_accepted_by_user_id !== $user->id || $activation->acceptance_idempotency_key !== $idempotencyKey) {
                    throw new DomainException('The Church activation token has already been used.');
                }

                return $this->existingResult($activation);
            }
            if ($activation->status !== ChurchActivationStatus::PENDING || ! $activation->token_expires_at || $activation->token_expires_at->lte(now())) {
                if ($activation->status === ChurchActivationStatus::PENDING && $activation->token_expires_at?->lte(now())) {
                    $activation->forceFill(['status' => ChurchActivationStatus::EXPIRED])->save();
                }
                throw new DomainException('The Church activation is unavailable or expired.');
            }
            if (strtolower($user->email) !== strtolower($activation->prospective_primary_email)
                || ($activation->prospective_user_id !== null && $activation->prospective_user_id !== $user->id)) {
                throw new DomainException('Sign in with the invited Primary account to accept this activation.');
            }

            $church = Church::query()->lockForUpdate()->findOrFail($activation->church_id);
            if ($church->is_active || ChurchMembership::query()->where('church_id', $church->id)->active()->primary()->exists()) {
                throw new DomainException('The provisional Church is no longer eligible for activation.');
            }
            [$market, $version, $price] = $this->validateCommercials($activation);
            $payer = $this->resolvePayer($activation, $church, $user);
            $this->ensureBillingProfile($payer, $activation, $church, $market, $user);
            $acceptedAt = CarbonImmutable::now();
            $membership = ChurchMembership::createPrimary($church, $user, [ChurchRole::ADMINISTRATOR, ChurchRole::COMMUNICATIONS]);
            $subscription = $this->subscriptions->startTrial($church, $payer, $market, $version, $price, $user->id, 'church_primary_activation', $acceptedAt);
            $church->forceFill(['is_active' => true, 'activated_at' => $acceptedAt])->save();
            if ($user->church_id === null) {
                $user->forceFill(['church_id' => $church->id])->save();
            }
            $activation->forceFill([
                'status' => ChurchActivationStatus::ACCEPTED, 'prospective_user_id' => $user->id,
                'accepted_at' => $acceptedAt, 'acceptance_idempotency_key' => $idempotencyKey,
                'terms_version' => $termsVersion, 'privacy_version' => $privacyVersion,
                'legal_accepted_at' => $acceptedAt, 'legal_accepted_by_user_id' => $user->id,
            ])->save();

            return new AcceptChurchPrimaryActivationResult($church->fresh(), $activation->fresh(), $membership->fresh('roles'), $payer->fresh(), $subscription->fresh('item'), true);
        }, 3);
    }

    /** @return array{0: PricingMarket, 1: PlanVersion, 2: Price} */
    private function validateCommercials(ChurchActivation $activation): array
    {
        $market = PricingMarket::query()->find($activation->pricing_market_id);
        $version = PlanVersion::query()->find($activation->plan_version_id);
        $price = Price::query()->find($activation->price_id);
        if (! $market || ! $version || ! $price || ! $version->status->isRuntimeEligible()) {
            throw new DomainException('The pinned commercial offer requires review.');
        }
        $decision = $this->pricing->quote($version, $market, $activation->billing_interval, now());
        if (! $decision->resolved || $decision->priceId !== $price->id || $price->plan_version_id !== $version->id) {
            throw new DomainException('The pinned commercial offer can no longer be honored.');
        }

        return [$market, $version, $price];
    }

    private function resolvePayer(ChurchActivation $activation, Church $church, User $actor): BillingAccount
    {
        [$owner, $field] = match ($activation->payer_type) {
            BillingAccountOwnerType::CHURCH => [$church, 'church_id'],
            BillingAccountOwnerType::ORGANIZATION => [Organization::query()->lockForUpdate()->findOrFail($activation->payer_organization_id), 'organization_id'],
            BillingAccountOwnerType::ORGANIZATION_UNIT => [OrganizationUnit::query()->lockForUpdate()->findOrFail($activation->payer_organization_unit_id), 'organization_unit_id'],
        };
        $accounts = BillingAccount::query()->where($field, $owner->getKey())->where('owner_type', $activation->payer_type->value)->where('status', BillingAccountStatus::ACTIVE->value)->lockForUpdate()->get();
        if ($accounts->count() > 1) {
            throw new DomainException('The intended payer has ambiguous active BillingAccounts.');
        }
        if ($accounts->count() === 1) {
            return $accounts->first();
        }

        if ($activation->payer_type !== BillingAccountOwnerType::CHURCH) {
            throw new DomainException('An active governed BillingAccount must already exist for an Organization or Unit payer.');
        }

        return $this->accounts->create($owner->name.' Billing', $owner, $actor->id, 'church_primary_activation');
    }

    private function ensureBillingProfile(BillingAccount $payer, ChurchActivation $activation, Church $church, PricingMarket $market, User $user): void
    {
        $profile = $payer->billingProfile()->first();
        if ($profile) {
            if ($profile->preferred_currency !== null && strtoupper($profile->preferred_currency) !== $market->currency) {
                throw new DomainException('The payer BillingProfile currency conflicts with the pinned PricingMarket.');
            }

            return;
        }
        BillingProfile::query()->create([
            'billing_account_id' => $payer->id, 'billing_name' => $payer->name,
            'billing_email' => $user->email, 'country_code' => $church->operating_country_code,
            'preferred_currency' => $market->currency, 'effective_from' => now(),
        ]);
    }

    private function existingResult(ChurchActivation $activation): AcceptChurchPrimaryActivationResult
    {
        $church = $activation->church;
        $membership = ChurchMembership::query()->where('church_id', $church->id)->where('user_id', $activation->legal_accepted_by_user_id)->active()->primary()->firstOrFail();
        $subscription = Subscription::query()->findOrFail($church->current_subscription_id);

        return new AcceptChurchPrimaryActivationResult($church, $activation, $membership, $subscription->billingAccount, $subscription, false);
    }
}
