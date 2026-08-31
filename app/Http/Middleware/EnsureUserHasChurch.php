<?php

namespace App\Http\Middleware;

use App\Enums\ChurchActivationStatus;
use App\Models\ChurchActivation;
use App\Support\OrganizationContext;
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
        session()->forget('active_organization_id');
        session(['active_workspace_type' => 'church']);
        app(OrganizationContext::class)->forgetResolved();
        app(TenantContext::class)->forgetResolved();
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

        return redirect()->route('workspaces.select');
    }
}
