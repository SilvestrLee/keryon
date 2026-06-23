<?php

namespace App\Policies;

use App\Models\CongregationMember;
use App\Models\User;

class CongregationMemberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->church_id !== null;
    }

    public function view(User $user, CongregationMember $member): bool
    {
        return $user->church_id !== null && $user->church_id === $member->church_id;
    }

    public function create(User $user): bool
    {
        return $user->church_id !== null;
    }

    public function update(User $user, CongregationMember $member): bool
    {
        return $user->church_id !== null && $user->church_id === $member->church_id;
    }

    public function delete(User $user, CongregationMember $member): bool
    {
        return $user->church_id !== null && $user->church_id === $member->church_id;
    }
}
