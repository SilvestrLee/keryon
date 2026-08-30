<?php

namespace App\Onboarding;

use App\Commercial\Pricing\PricingMarketResolver;
use App\Enums\BillingAccountOwnerType;
use App\Enums\ChurchActivationStatus;
use App\Models\Church;
use App\Models\ChurchActivation;
use App\Models\Organization;
use App\Models\OrganizationUnit;
use App\Models\User;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class ProvisionChurch
{
    public function __construct(
        private readonly ChurchSlugService $slugs,
        private readonly PricingMarketResolver $markets,
        private readonly CurrentLaunchProductResolver $products,
    ) {}

    public function execute(ProvisionChurchData $data): ProvisionChurchResult
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return $this->provisionOnce($data);
            } catch (UniqueConstraintViolationException $exception) {
                if ($existing = ChurchActivation::query()->where('idempotency_key', $data->idempotencyKey)->first()) {
                    return new ProvisionChurchResult($existing->church, $existing, false);
                }
                if ($attempt === 2) {
                    throw $exception;
                }
            }
        }

        throw new DomainException('Unable to provision the Church safely.');
    }

    private function provisionOnce(ProvisionChurchData $data): ProvisionChurchResult
    {
        if ($existing = ChurchActivation::query()->where('idempotency_key', $data->idempotencyKey)->first()) {
            return new ProvisionChurchResult($existing->church, $existing, false);
        }
        $country = strtoupper(trim($data->operatingCountryCode));
        if (! preg_match('/^[A-Z]{2}$/', $country) || ! in_array($data->timezone, timezone_identifiers_list(), true)) {
            throw new DomainException('Provisioning requires a valid operating country and timezone.');
        }
        if (! filter_var($data->prospectivePrimaryEmail, FILTER_VALIDATE_EMAIL) || $data->operatorReference === '' || $data->origin === '' || $data->idempotencyKey === '') {
            throw new DomainException('Provisioning identity and provenance are required.');
        }
        $this->validatePayer($data);

        return DB::transaction(function () use ($data, $country): ProvisionChurchResult {
            if ($existing = ChurchActivation::query()->where('idempotency_key', $data->idempotencyKey)->lockForUpdate()->first()) {
                return new ProvisionChurchResult($existing->church, $existing, false);
            }
            $slug = $this->slugs->available($data->requestedSlug ?: $data->churchName, $data->requestedSlug !== null);
            $church = Church::query()->create([
                'name' => trim($data->churchName), 'slug' => $slug, 'timezone' => $data->timezone,
                'operating_country_code' => $country, 'is_active' => false, 'activated_at' => null,
            ]);
            $marketDecision = $this->markets->forCountry($country);
            $version = $price = null;
            $status = ChurchActivationStatus::COMMERCIAL_REVIEW;
            if ($marketDecision->resolved()) {
                [$version, $price] = $this->products->resolve($marketDecision->market, $data->billingInterval);
                $status = ChurchActivationStatus::PENDING;
            }
            $activation = ChurchActivation::query()->create([
                'church_id' => $church->id,
                'prospective_primary_email' => strtolower(trim($data->prospectivePrimaryEmail)),
                'prospective_user_id' => User::query()->whereRaw('LOWER(email) = ?', [strtolower(trim($data->prospectivePrimaryEmail))])->value('id'),
                'status' => $status, 'provisioned_by_reference' => $data->operatorReference,
                'provisioning_origin' => $data->origin, 'pricing_market_id' => $marketDecision->market?->id,
                'plan_version_id' => $version?->id, 'price_id' => $price?->id,
                'billing_interval' => $data->billingInterval, 'payer_type' => $data->payerType,
                'payer_church_id' => $data->payerType === BillingAccountOwnerType::CHURCH ? $church->id : null,
                'payer_organization_id' => $data->payerOrganizationId,
                'payer_organization_unit_id' => $data->payerOrganizationUnitId,
                'idempotency_key' => $data->idempotencyKey,
                'organization_id' => $data->organizationId, 'organization_unit_id' => $data->organizationUnitId,
            ]);

            return new ProvisionChurchResult($church, $activation, true);
        }, 3);
    }

    private function validatePayer(ProvisionChurchData $data): void
    {
        if ($data->payerType === BillingAccountOwnerType::ORGANIZATION && ! Organization::query()->whereKey($data->payerOrganizationId)->exists()) {
            throw new DomainException('The intended Organization payer does not exist.');
        }
        if ($data->payerType === BillingAccountOwnerType::ORGANIZATION_UNIT && ! OrganizationUnit::query()->whereKey($data->payerOrganizationUnitId)->exists()) {
            throw new DomainException('The intended Organization Unit payer does not exist.');
        }
    }
}
