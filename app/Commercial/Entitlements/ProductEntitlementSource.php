<?php

namespace App\Commercial\Entitlements;

use App\Models\Church;
use App\Models\PlanVersion;

interface ProductEntitlementSource
{
    public function planVersionFor(Church $church): ?PlanVersion;

    public function identifier(): string;
}
