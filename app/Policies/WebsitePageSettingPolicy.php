<?php

namespace App\Policies;

use App\Enums\Capability;
use App\Models\User;
use App\Models\WebsitePageSetting;
use App\Policies\Concerns\ResolvesTenantMembership;

/**
 * K-WEB-V1-001D-B §27 — page-setting mutation reuses the existing
 * `website.content.*` vocabulary, exactly like every other Website
 * content model (`WebsiteHomeContentPolicy` et al.). No new Capability
 * was introduced or found necessary.
 */
class WebsitePageSettingPolicy
{
    use ResolvesTenantMembership;

    public function viewAny(User $user): bool
    {
        return $this->membershipFor($user)?->hasCapability(Capability::WebsiteContentView) ?? false;
    }

    public function view(User $user, WebsitePageSetting $setting): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::WebsiteContentView)
            && $setting->church_id === $membership->church_id;
    }

    public function create(User $user): bool
    {
        return $this->membershipFor($user)?->hasCapability(Capability::WebsiteContentManage) ?? false;
    }

    public function update(User $user, WebsitePageSetting $setting): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::WebsiteContentManage)
            && $setting->church_id === $membership->church_id;
    }
}
