<?php

namespace App\Support;

use App\Models\ChurchMembership;
use Illuminate\Support\Facades\Auth;

/**
 * Resolves the currently active Church for the authenticated User, from
 * their active ChurchMembership — the seam that replaces direct reads of
 * Auth::user()->church_id throughout tenant scoping. See Keryon Blueprint
 * v1.4.1 §4/§20 and its companion engineering document §2-§3.
 *
 * A session may remember a selected church, but that selection is never
 * trusted on its own — every resolution re-validates membership status
 * and Church.is_active. See engineering document §6.
 */
class TenantContext
{
    protected bool $resolved = false;

    protected ?ChurchMembership $membership = null;

    /**
     * The user ID the cached resolution above belongs to. Re-resolves
     * whenever the authenticated user differs from this — a single
     * long-lived request never switches Auth::user(), but tests routinely
     * call actingAs() more than once per test, and a stale cross-user
     * cache here would be exactly the kind of tenant leak this class
     * exists to prevent.
     */
    protected false|null|int $resolvedForUserId = false;

    public function currentMembership(): ?ChurchMembership
    {
        $currentUserId = Auth::check() ? Auth::id() : null;

        if (! $this->resolved || $this->resolvedForUserId !== $currentUserId) {
            $this->membership = $this->resolve();
            $this->resolved = true;
            $this->resolvedForUserId = $currentUserId;
        }

        return $this->membership;
    }

    public function currentChurch(): ?\App\Models\Church
    {
        return $this->currentMembership()?->church;
    }

    public function currentChurchId(): ?int
    {
        return $this->currentMembership()?->church_id;
    }

    public function hasContext(): bool
    {
        return $this->currentMembership() !== null;
    }

    /**
     * Forces re-resolution on the next call. Intended for tests and for
     * the rare in-request case where the acting user's membership set
     * legitimately changes mid-request (e.g. immediately after creating
     * the first membership during Church setup).
     */
    public function forgetResolved(): void
    {
        $this->resolved = false;
        $this->membership = null;
        $this->resolvedForUserId = false;
    }

    protected function resolve(): ?ChurchMembership
    {
        if (! Auth::check()) {
            return null;
        }

        $user = Auth::user();

        $activeMemberships = $user->memberships()
            ->active()
            ->with('church')
            ->get()
            ->filter(fn (ChurchMembership $membership) => $membership->church !== null && $membership->church->is_active);

        if ($activeMemberships->isEmpty()) {
            return null;
        }

        if ($activeMemberships->count() === 1) {
            return $activeMemberships->first();
        }

        // More than one active membership. Only a valid, still-active
        // session selection may resolve it — never an implicit choice.
        // See Blueprint v1.4.1 preflight decision #6 / K-IDENTITY-001A §N.
        $selectedChurchId = session('active_church_id');

        if ($selectedChurchId !== null) {
            $selected = $activeMemberships->firstWhere('church_id', (int) $selectedChurchId);

            if ($selected !== null) {
                return $selected;
            }
        }

        return null;
    }
}
