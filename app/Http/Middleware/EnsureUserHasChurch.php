<?php

namespace App\Http\Middleware;

use App\Enums\ChurchActivationStatus;
use App\Models\ChurchActivation;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Converted from a raw users.church_id check to TenantContext-backed
 * membership semantics. Kept as "remain but change semantics" per
 * K-IDENTITY-001A §26 — same class name, same overall shape.
 */
class EnsureUserHasChurch
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check()) {
            return $next($request);
        }

        if (app(TenantContext::class)->hasContext()) {
            return $next($request);
        }

        if ($request->is('admin/setup')) {
            return $next($request);
        }

        if (! auth()->user()->activeMemberships()->exists()) {
            $pendingActivation = ChurchActivation::query()
                ->where(fn ($query) => $query->where('prospective_user_id', auth()->id())->orWhere('prospective_primary_email', strtolower(auth()->user()->email)))
                ->whereIn('status', [ChurchActivationStatus::PENDING->value, ChurchActivationStatus::COMMERCIAL_REVIEW->value])
                ->exists();
            if ($pendingActivation) {
                return redirect('/admin/setup')->with('status', 'Use your activation invitation to activate the provisioned Church workspace.');
            }

            return redirect('/admin/setup');
        }

        // Active memberships exist but TenantContext could not resolve
        // one of them (e.g. more than one active membership with no
        // valid session selection). Do not send this user into "create a
        // new church" — that would be actively wrong. A church-selection
        // screen is future scope (Blueprint v1.4.1 §5); no such user
        // exists in current data, so this fails closed rather than
        // guessing. See K-IDENTITY-001A §17/§44.
        abort(403, 'Unable to determine your active church. Contact support.');
    }
}
