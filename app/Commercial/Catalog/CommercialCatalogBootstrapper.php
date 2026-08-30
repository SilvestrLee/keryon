<?php

namespace App\Commercial\Catalog;

use App\Commercial\Entitlements\EntitlementValue;
use App\Enums\EntitlementKey;
use App\Enums\PlanVersionStatus;
use App\Models\Plan;
use App\Models\PlanVersion;
use DomainException;
use Illuminate\Support\Facades\DB;

class CommercialCatalogBootstrapper
{
    public function __construct(private readonly CommercialCatalogService $catalog) {}

    public function bootstrap(): PlanVersion
    {
        return DB::transaction(function (): PlanVersion {
            $plan = Plan::query()->firstOrCreate(
                ['slug' => 'keryon'],
                ['name' => 'Keryon', 'status' => 'draft'],
            );
            $version = PlanVersion::query()->firstOrCreate(
                ['plan_id' => $plan->id, 'version_code' => 'keryon-2026-1'],
                ['status' => PlanVersionStatus::DRAFT],
            );
            $expected = [
                EntitlementKey::FaithFlowEnabled->value => true,
                EntitlementKey::WebsiteEnabled->value => true,
                EntitlementKey::DesignEnabled->value => true,
                EntitlementKey::MarketplacePremiumEnabled->value => false,
            ];

            if ($version->status === PlanVersionStatus::DRAFT) {
                foreach ($expected as $key => $value) {
                    $this->catalog->defineEntitlement($version, EntitlementKey::from($key), EntitlementValue::boolean($value));
                }

                return $this->catalog->publish($version);
            }

            $actual = $version->entitlements->mapWithKeys(
                fn ($entitlement): array => [$entitlement->entitlement_key->value => $entitlement->value()->raw()],
            )->all();
            if (collect($actual)->sortKeys()->all() !== collect($expected)->sortKeys()->all()) {
                throw new DomainException('The existing Keryon 2026.1 catalogue does not match the governed definition.');
            }

            return $version->fresh(['plan', 'entitlements']);
        });
    }
}
