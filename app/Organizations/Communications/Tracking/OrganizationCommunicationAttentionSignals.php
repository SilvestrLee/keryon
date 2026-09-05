<?php

namespace App\Organizations\Communications\Tracking;

use App\Enums\OrganizationCapability;
use App\Enums\OrganizationCommunicationRevisionState;
use App\Enums\OrganizationCommunicationState;
use App\Models\OrganizationCommunicationRevision;
use App\Organizations\OrganizationScopeResolver;
use App\Support\OrganizationContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * K-ORG-COMMS-001F §29-§33 — bounded, deterministic Organization Dashboard
 * attention signals for Communications. The dashboard question stays
 * "what needs my attention", not an analytics surface (§29/§31): exactly
 * two truthful, non-inferred rules, both reusing the same
 * `OrganizationCommunicationDeliveryOutcomeResolver` precedence the
 * Tracking tab uses (§58) rather than a second definition of "outcome".
 *
 * 1. Approved but not yet distributed — the Organization has an action
 *    available to it.
 * 2. Distributed, a `recommended_response_on` date was actually
 *    configured, that date has passed, and at least one Church is still
 *    factually Available (unresponded). §33 — no configured date means
 *    no "overdue" claim; silence is never interpreted as urgency.
 *
 * No engagement/adoption/participation score (§31) — counts only.
 */
final class OrganizationCommunicationAttentionSignals
{
    public function __construct(
        private readonly OrganizationContext $context,
        private readonly OrganizationScopeResolver $scope,
    ) {}

    /** @return list<array{title: string, detail: string, status: string}> */
    public function forDashboard(int $limit = 6): array
    {
        $membership = $this->context->currentMembership();

        if ($membership === null || ! $this->scope->hasOrganizationCapability(OrganizationCapability::CommunicationsView)) {
            return [];
        }

        $unitIds = $this->scope->unitsInScope(OrganizationCapability::CommunicationsView, includeArchived: true)
            ->select('organization_units.id');

        $approvedNotDistributed = $this->approvedNotDistributed($membership->organization_id, $unitIds, $limit);

        $remaining = $limit - $approvedNotDistributed->count();
        $overdue = $remaining > 0
            ? $this->responseOverdue($membership->organization_id, $unitIds, $remaining)
            : collect();

        return $approvedNotDistributed->merge($overdue)->take($limit)->values()->all();
    }

    /**
     * @return Collection<int, array{title: string, detail: string, status: string}>
     *
     * Deliberately `collect($query->get(...)->map(...)->all())`, not a
     * bare `->map()` chain: `Eloquent\Collection::map()` only downgrades
     * to a base `Support\Collection` when it actually has a non-Model
     * item to inspect — an *empty* result stays an Eloquent Collection,
     * and `forDashboard()`'s later `merge()` against a real array-item
     * Collection would then crash calling `getKey()` on an array. Same
     * class of bug already fixed once in K-ORG-COMMS-001B
     * (`OrganizationCommunicationQuery::auditEvents()`) — `collect()`
     * forces a genuine base Collection unconditionally, regardless of
     * emptiness.
     */
    private function approvedNotDistributed(int $organizationId, $unitIds, int $limit): Collection
    {
        $revisions = OrganizationCommunicationRevision::query()
            ->where('state', OrganizationCommunicationRevisionState::APPROVED->value)
            ->whereHas('communication', function ($query) use ($organizationId, $unitIds): void {
                $query->where('organization_id', $organizationId)
                    ->whereIn('governing_unit_id', $unitIds)
                    ->where('state', '!=', OrganizationCommunicationState::CLOSED->value);
            })
            ->orderByDesc('approved_at')
            ->limit($limit)
            ->get(['id', 'title'])
            ->map(fn (OrganizationCommunicationRevision $revision): array => [
                'title' => $revision->title ?? 'Untitled communication',
                'detail' => 'Approved and ready to share — not yet distributed to any Church.',
                'status' => 'Needs distribution',
            ])
            ->all();

        return collect($revisions);
    }

    /** @return Collection<int, array{title: string, detail: string, status: string}> */
    private function responseOverdue(int $organizationId, $unitIds, int $limit): Collection
    {
        $candidates = OrganizationCommunicationRevision::query()
            ->where('state', OrganizationCommunicationRevisionState::DISTRIBUTED->value)
            ->whereNotNull('recommended_response_on')
            ->where('recommended_response_on', '<', now()->toDateString())
            ->whereHas('communication', function ($query) use ($organizationId, $unitIds): void {
                $query->where('organization_id', $organizationId)
                    ->whereIn('governing_unit_id', $unitIds)
                    ->where('state', '!=', OrganizationCommunicationState::CLOSED->value);
            })
            ->orderBy('recommended_response_on')
            ->get(['id', 'title', 'recommended_response_on']);

        if ($candidates->isEmpty()) {
            return collect();
        }

        // §54/§57 — one grouped aggregate query, never a per-revision
        // count and never a PHP-side ->get()->groupBy() over raw
        // delivery rows.
        $outcomeCase = OrganizationCommunicationDeliveryOutcomeResolver::sqlCaseExpression();
        $availableCounts = DB::table('organization_communication_deliveries as deliveries')
            ->leftJoin('organization_communication_imports as imports', 'imports.organization_communication_delivery_id', '=', 'deliveries.id')
            ->whereIn('deliveries.organization_communication_revision_id', $candidates->pluck('id'))
            ->selectRaw('deliveries.organization_communication_revision_id as revision_id')
            ->selectRaw("{$outcomeCase} as outcome")
            ->selectRaw('count(*) as total')
            ->groupBy('deliveries.organization_communication_revision_id', 'outcome')
            ->having('outcome', '=', OrganizationCommunicationDeliveryOutcomeResolver::AVAILABLE)
            ->pluck('total', 'revision_id');

        $overdue = $candidates
            ->filter(fn (OrganizationCommunicationRevision $revision): bool => ($availableCounts[$revision->id] ?? 0) > 0)
            ->take($limit)
            ->map(fn (OrganizationCommunicationRevision $revision): array => [
                'title' => $revision->title ?? 'Untitled communication',
                'detail' => ($availableCounts[$revision->id] ?? 0).' '.str('Church')->plural($availableCounts[$revision->id] ?? 0).' have not yet responded — recommended by '.$revision->recommended_response_on->format('j M Y').'.',
                'status' => 'Response overdue',
            ])
            ->values()
            ->all();

        // Same emptiness caveat as `approvedNotDistributed()` above.
        return collect($overdue);
    }
}
