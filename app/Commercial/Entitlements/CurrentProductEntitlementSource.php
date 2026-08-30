<?php

namespace App\Commercial\Entitlements;

use App\Enums\PlanStatus;
use App\Models\Church;
use App\Models\PlanVersion;

class CurrentProductEntitlementSource implements ProductEntitlementSource
{
    public function planVersionFor(Church $church): ?PlanVersion
    {
        return PlanVersion::query()
            ->where('version_code', config('commercial.current_product.plan_version'))
            ->whereHas('plan', fn ($query) => $query
                ->where('slug', config('commercial.current_product.plan_slug'))
                ->where('status', PlanStatus::ACTIVE->value))
            ->first();
    }

    public function identifier(): string
    {
        return 'current_product_default';
    }
}
