<?php

namespace App\Policies;

use App\Enums\Capability;
use App\Models\User;
use App\Models\WebsiteSettings;
use App\Policies\Concerns\ResolvesTenantMembership;

/**
 * K-CHURCHWEB-001B §19/§25/§34 — Website Content authorizes against the
 * pre-existing `website.content.*` vocabulary, exactly as K-WEB-001A
 * found already reserved and granted to Communications. Covers both the
 * `footer_note` content field and the `theme` selection field — both are
 * WebsiteSettings, not separate objects; `Capability::WebsiteThemeManage`
 * remains available for a future, more granular UI if Product Office
 * ever wants to split "can edit content" from "can change theme".
 */
class WebsiteSettingsPolicy
{
    use ResolvesTenantMembership;

    public function viewAny(User $user): bool
    {
        return $this->membershipFor($user)?->hasCapability(Capability::WebsiteContentView) ?? false;
    }

    public function view(User $user, WebsiteSettings $settings): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::WebsiteContentView)
            && $settings->church_id === $membership->church_id;
    }

    public function create(User $user): bool
    {
        return $this->membershipFor($user)?->hasCapability(Capability::WebsiteContentManage) ?? false;
    }

    public function update(User $user, WebsiteSettings $settings): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::WebsiteContentManage)
            && $settings->church_id === $membership->church_id;
    }

    /**
     * K-CHURCHWEB-001C §5/§29 — Product Office correction: theme selection
     * authorizes against `WebsiteThemeManage` specifically, independent of
     * `update()` (which governs `footer_note`, a content field). Both
     * fields live on the same row, but the *action* of changing the theme
     * is gated separately — a Communications member with only
     * `WebsiteContentManage` (impossible today, since both are granted
     * together, but the distinction must hold on its own) still could not
     * change the theme without `WebsiteThemeManage` too.
     */
    public function changeTheme(User $user, WebsiteSettings $settings): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::WebsiteThemeManage)
            && $settings->church_id === $membership->church_id;
    }
}
