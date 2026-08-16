<?php

namespace App\Policies;

use App\Models\ContentItem;
use App\Models\User;

class ContentItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->church_id !== null;
    }

    public function view(User $user, ContentItem $contentItem): bool
    {
        return $user->church_id !== null && $user->church_id === $contentItem->church_id;
    }

    public function create(User $user): bool
    {
        return $user->church_id !== null;
    }

    public function update(User $user, ContentItem $contentItem): bool
    {
        return $user->church_id !== null && $user->church_id === $contentItem->church_id;
    }

    public function delete(User $user, ContentItem $contentItem): bool
    {
        return $user->church_id !== null && $user->church_id === $contentItem->church_id;
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
