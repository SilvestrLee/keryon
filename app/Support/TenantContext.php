<?php

namespace App\Support;

use App\Models\Church;
use App\Models\ChurchMembership;
use App\Support\Exceptions\UntrustedTenantExecutionException;
use Closure;
use Illuminate\Support\Facades\Auth;
use LogicException;

/**
 * Resolves the currently active Church for the authenticated User, from
 * their active ChurchMembership — the seam that replaces direct reads of
 * Auth::user()->church_id throughout tenant scoping. See Keryon Blueprint
 * v1.4.1 §4/§20 and its companion engineering document §2-§3.
 *
 * A session may remember a selected church, but that selection is never
 * trusted on its own — every resolution re-validates membership status
 * and Church.is_active. See engineering document §6.
 *
 * K-ASYNC-001 §13 extends this class with a second, narrowly-scoped
 * resolution mode for background execution — `runFor()` — alongside the
 * original Auth/session-derived mode above, which is completely unchanged
 * for every existing HTTP call site. The two modes never mix within one
 * instance: while a `runFor()` call is active, `currentMembership()`/
 * `currentChurchId()`/`currentChurch()` all return the *restored* identity
 * instead of resolving from `Auth::user()`, and the restored identity is
 * always cleared again before `runFor()` returns — including on exception.
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

    /**
     * Set only for the duration of a `runFor()` call — see §13 above.
     */
    protected bool $overridden = false;

    protected ?int $overrideChurchId = null;

    protected ?ChurchMembership $overrideMembership = null;

    public function currentMembership(): ?ChurchMembership
    {
        if ($this->overridden) {
            return $this->overrideMembership;
        }

        $currentUserId = Auth::check() ? Auth::id() : null;

        if (! $this->resolved || $this->resolvedForUserId !== $currentUserId) {
            $this->membership = $this->resolve();
            $this->resolved = true;
            $this->resolvedForUserId = $currentUserId;
        }

        return $this->membership;
    }

    public function currentChurch(): ?Church
    {
        if ($this->overridden) {
            return $this->overrideMembership?->church ?? Church::find($this->overrideChurchId);
        }

        return $this->currentMembership()?->church;
    }

    public function currentChurchId(): ?int
    {
        if ($this->overridden) {
            return $this->overrideChurchId;
        }

        return $this->currentMembership()?->church_id;
    }

    public function hasContext(): bool
    {
        return $this->currentChurchId() !== null;
    }

    /**
     * The DISPATCH-TIME half of K-ASYNC-001's execution-context contract
     * (§6/§10) — captures the *current*, already-trusted (Auth/session-
     * derived) identity as a small, serializable
     * `TenantExecutionContext` a Job can carry across the queue boundary.
     * There is no parameter through which a caller could capture an
     * arbitrary Church/User combination — only whatever `currentMembership
     * ()` has already resolved via the normal, trusted HTTP path. Throws
     * if there is no active context to capture; capturing "nothing" would
     * silently produce a job with no tenant owner, which is exactly the
     * failure mode §7 forbids.
     *
     * System-originated contexts (§9, no requesting human) are not built
     * via this method — construct `TenantExecutionContext` directly with
     * `actorUserId: null` for that case.
     */
    public function capture(): TenantExecutionContext
    {
        $membership = $this->currentMembership();

        if ($membership === null) {
            throw new LogicException('Cannot capture a background execution context — there is no active tenant context to capture.');
        }

        return new TenantExecutionContext($membership->church_id, $membership->user_id);
    }

    /**
     * The EXECUTION-TIME half (§10/§11/§12/§13) — the *only* way to
     * activate a restored tenant identity, deliberately: wrapping
     * activation/cleanup in one method (rather than exposing separate
     * activate()/clear() calls) makes "restore → execute → always clear"
     * structural, not a caller discipline that can be forgotten. Re-
     * validates the context fresh against the database before activating
     * anything (§11/§12 — a membership revoked, or a Church deactivated,
     * between dispatch and execution must be caught here, not trusted from
     * the queue payload) and always restores prior state in a `finally`
     * block, so an exception thrown by `$callback` still clears the
     * override before propagating.
     *
     * @throws UntrustedTenantExecutionException if the Church is missing/
     *         inactive, or (when the context has an actor) that actor no
     *         longer has an active membership at this Church.
     */
    public function runFor(TenantExecutionContext $context, Closure $callback): mixed
    {
        $membership = $this->resolveTrusted($context);

        $this->overridden = true;
        $this->overrideChurchId = $context->churchId;
        $this->overrideMembership = $membership;

        try {
            return $callback();
        } finally {
            $this->overridden = false;
            $this->overrideChurchId = null;
            $this->overrideMembership = null;
            $this->forgetResolved();
        }
    }

    /**
     * Fresh-from-database re-validation only — never trusts anything about
     * the payload beyond the two durable IDs it carries. Returns null (not
     * an exception) for a legitimately actor-less, system-originated
     * context (§9); throws for every case where the claimed identity is no
     * longer trustworthy.
     */
    protected function resolveTrusted(TenantExecutionContext $context): ?ChurchMembership
    {
        $church = Church::find($context->churchId);

        if ($church === null || ! $church->is_active) {
            throw new UntrustedTenantExecutionException(
                "Church [{$context->churchId}] is missing or inactive — this execution context is no longer trusted."
            );
        }

        if ($context->actorUserId === null) {
            return null;
        }

        $membership = ChurchMembership::query()
            ->where('church_id', $context->churchId)
            ->where('user_id', $context->actorUserId)
            ->active()
            ->first();

        if ($membership === null) {
            throw new UntrustedTenantExecutionException(
                "User [{$context->actorUserId}] no longer has an active membership at Church [{$context->churchId}] — this execution context is no longer trusted."
            );
        }

        return $membership;
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
