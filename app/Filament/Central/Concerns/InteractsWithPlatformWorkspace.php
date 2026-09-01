<?php

namespace App\Filament\Central\Concerns;

use App\Enums\PlatformCapability;
use App\Models\PlatformMembership;
use App\Support\PlatformContext;

trait InteractsWithPlatformWorkspace
{
    public static function canAccess(): bool
    {
        return app(PlatformContext::class)->hasCapability(static::requiredCapability());
    }

    abstract protected static function requiredCapability(): PlatformCapability;

    public function platformMembership(): PlatformMembership
    {
        return app(PlatformContext::class)->currentMembership()
            ?? abort(403, 'Active platform access is required.');
    }
}
