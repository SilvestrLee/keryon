<?php

namespace App\Commercial\Catalog;

use App\Commercial\Entitlements\EntitlementValue;
use App\Enums\EntitlementKey;
use App\Enums\EntitlementValueType;
use App\Enums\PlanStatus;
use App\Enums\PlanVersionStatus;
use App\Models\Plan;
use App\Models\PlanVersion;
use App\Models\PlanVersionEntitlement;
use DomainException;
use Illuminate\Support\Facades\DB;

class CommercialCatalogService
{
    public function createPlan(string $slug, string $name): Plan
    {
        return Plan::query()->create([
            'slug' => $slug,
            'name' => $name,
            'status' => PlanStatus::DRAFT,
        ]);
    }

    public function createVersion(Plan $plan, string $versionCode, ?\DateTimeInterface $effectiveFrom = null): PlanVersion
    {
        return $plan->versions()->create([
            'version_code' => $versionCode,
            'status' => PlanVersionStatus::DRAFT,
            'effective_from' => $effectiveFrom,
        ]);
    }

    public function defineEntitlement(PlanVersion $version, EntitlementKey $key, EntitlementValue $value): PlanVersionEntitlement
    {
        if ($version->status !== PlanVersionStatus::DRAFT) {
            throw new DomainException('Entitlements may only be defined on a draft PlanVersion.');
        }
        if ($key->valueType() !== $value->type) {
            throw new DomainException("{$key->value} requires a {$key->valueType()->value} value.");
        }

        return $version->entitlements()->updateOrCreate(
            ['entitlement_key' => $key->value],
            [
                'value_type' => $value->type->value,
                'boolean_value' => $value->type === EntitlementValueType::BOOLEAN ? $value->booleanValue() : null,
                'integer_value' => $value->type === EntitlementValueType::INTEGER ? $value->integerValue() : null,
            ],
        );
    }

    public function publish(PlanVersion $version): PlanVersion
    {
        return DB::transaction(function () use ($version): PlanVersion {
            $locked = PlanVersion::query()->lockForUpdate()->findOrFail($version->id);
            if ($locked->status !== PlanVersionStatus::DRAFT) {
                throw new DomainException('Only a draft PlanVersion may be published.');
            }
            if (! $locked->entitlements()->exists()) {
                throw new DomainException('A PlanVersion requires at least one entitlement before publication.');
            }

            $locked->forceFill([
                'status' => PlanVersionStatus::ACTIVE,
                'published_at' => now(),
                'effective_from' => $locked->effective_from ?? now(),
            ])->save();
            $locked->plan()->update(['status' => PlanStatus::ACTIVE]);

            return $locked->fresh(['plan', 'entitlements']);
        });
    }

    public function retire(PlanVersion $version, ?\DateTimeInterface $effectiveUntil = null): PlanVersion
    {
        $version->forceFill([
            'status' => PlanVersionStatus::RETIRED,
            'effective_until' => $effectiveUntil ?? now(),
        ])->save();

        return $version->fresh();
    }
}
