<?php

namespace App\Policies;

use App\Enums\Capability;
use App\Models\ContentItem;
use App\Models\User;
use App\Policies\Concerns\ResolvesTenantMembership;

/**
 * Converted from a flat `$user->church_id === $contentItem->church_id`
 * check to capability-based authorization. Content Studio is a
 * Communications responsibility — Administrator and Care do not inherit
 * it. Self-approval remains acceptable for MVP (Blueprint v1.4.1 §11), so
 * every lifecycle action authorizes against the same `content.manage`
 * capability as a plain update — no separate `content.approve` capability.
 * See K-AUTH-001B §30-§31.
 */
class ContentItemPolicy
{
    use ResolvesTenantMembership;

    public function viewAny(User $user): bool
    {
        return $this->membershipFor($user)?->hasCapability(Capability::ContentView) ?? false;
    }

    public function view(User $user, ContentItem $contentItem): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::ContentView)
            && $contentItem->church_id === $membership->church_id;
    }

    public function create(User $user): bool
    {
        return $this->membershipFor($user)?->hasCapability(Capability::ContentManage) ?? false;
    }

    public function update(User $user, ContentItem $contentItem): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::ContentManage)
            && $contentItem->church_id === $membership->church_id;
    }

    public function delete(User $user, ContentItem $contentItem): bool
    {
        $membership = $this->membershipFor($user);

        return $membership !== null
            && $membership->hasCapability(Capability::ContentManage)
            && $contentItem->church_id === $membership->church_id;
    }

    public function submitForReview(User $user, ContentItem $contentItem): bool
    {
        return $this->update($user, $contentItem);
    }

    public function approve(User $user, ContentItem $contentItem): bool
    {
        return $this->update($user, $contentItem);
    }

    public function requestChanges(User $user, ContentItem $contentItem): bool
    {
        return $this->update($user, $contentItem);
    }
}
