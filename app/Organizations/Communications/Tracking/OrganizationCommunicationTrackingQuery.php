<?php

namespace App\Organizations\Communications\Tracking;

use App\Enums\ChurchOrganizationAssignmentStatus;
use App\Enums\OrganizationCommunicationDeclineReasonCode;
use App\Enums\OrganizationCommunicationTargetMode;
use App\Models\OrganizationCommunication;
use App\Models\OrganizationCommunicationDistribution;
use App\Models\OrganizationCommunicationRevision;
use App\Organizations\Communications\Tracking\Dto\OrganizationCommunicationChurchOutcome;
use App\Organizations\Communications\Tracking\Dto\OrganizationCommunicationDistributionTracking;
use App\Organizations\Communications\Tracking\Dto\OrganizationCommunicationTrackingSummary;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * K-ORG-COMMS-001F §14 — the single canonical Organization-side read
 * model for delivery-outcome tracking. Every method authorizes against
 * `OrganizationCommunicationPolicy::viewDistributions()` (§60 — reusing
 * the existing `organization.communications.deliveries.view` capability,
 * not a new one) before touching anything, uses `OrganizationContext`
 * only, and never joins into `content_items`, `campaigns`,
 * `media_assets`, `website_publications`, FaithFlow, `prayer_requests`,
 * or `congregation_members` (§38/§68). The only local-Church-derived
 * fact this ever touches is the existence + timestamp of an
 * `organization_communication_imports` row (§39) — never
 * `organization_communication_import_results`, and never
 * `imported_by_church_membership_id`/`responded_by_church_membership_id`
 * (§40).
 */
final class OrganizationCommunicationTrackingQuery
{
    public function __construct(private readonly OrganizationCommunicationDeliveryOutcomeResolver $resolver) {}

    /**
     * K-ORG-COMMS-001F §21/§22/§77 — one summary per revision that has
     * ever been distributed, most recent version first. Within one
     * revision, a Church that received it through more than one
     * distribution is counted exactly once, using its single most-
     * progressed outcome (§21, Product Office decision A).
     *
     * @return list<OrganizationCommunicationTrackingSummary>
     */
    public function communicationSummary(OrganizationCommunication $communication): array
    {
        Gate::authorize('viewDistributions', $communication);

        $revisionIds = OrganizationCommunicationDistribution::query()
            ->where('organization_communication_id', $communication->id)
            ->distinct()
            ->pluck('organization_communication_revision_id');

        if ($revisionIds->isEmpty()) {
            return [];
        }

        return OrganizationCommunicationRevision::query()
            ->whereIn('id', $revisionIds)
            ->orderByDesc('version')
            ->get(['id', 'version'])
            ->map(fn (OrganizationCommunicationRevision $revision): OrganizationCommunicationTrackingSummary => $this->summaryForRevision($revision))
            ->all();
    }

    /**
     * K-ORG-COMMS-001F §23 — one distribution's own outcome counts, no
     * cross-distribution de-duplication (each distribution's deliveries
     * are already one-per-Church by the existing 001C unique
     * constraint).
     */
    public function distributionTracking(OrganizationCommunicationDistribution $distribution): OrganizationCommunicationDistributionTracking
    {
        Gate::authorize('viewDistributions', $distribution->communication);

        $rows = DB::table('organization_communication_deliveries as deliveries')
            ->leftJoin('organization_communication_imports as imports', 'imports.organization_communication_delivery_id', '=', 'deliveries.id')
            ->where('deliveries.organization_communication_distribution_id', $distribution->id)
            ->selectRaw(OrganizationCommunicationDeliveryOutcomeResolver::sqlCaseExpression().' as outcome')
            ->selectRaw('count(*) as total')
            ->groupBy('outcome')
            ->pluck('total', 'outcome');

        $counts = $rows->all();
        $recipientCount = array_sum($counts);

        return new OrganizationCommunicationDistributionTracking(
            distributionId: $distribution->id,
            revisionVersion: $distribution->revision->version,
            targetSummary: $this->targetSummary($distribution),
            state: $distribution->state->value,
            stateLabel: $distribution->state->label(),
            snapshotAt: $distribution->snapshot_at?->toDateTimeString(),
            recipientCount: $recipientCount,
            counts: $counts,
        );
    }

    /**
     * K-ORG-COMMS-001F §24-§27/§53 — the bounded, paginated recipient
     * list for one distribution. SQL-paginated (never `->get()` over the
     * full recipient set), with outcome/search filtering applied at the
     * database.
     */
    public function recipients(
        OrganizationCommunicationDistribution $distribution,
        string $outcome = '',
        string $search = '',
        int $perPage = 25,
    ): LengthAwarePaginator {
        Gate::authorize('viewDistributions', $distribution->communication);

        $query = $this->recipientRowsQuery($distribution->id);

        if ($outcome !== '' && in_array($outcome, OrganizationCommunicationDeliveryOutcomeResolver::precedence(), true)) {
            $query->having('outcome', '=', $outcome);
        }

        $term = trim($search);
        if ($term !== '') {
            $query->where(function (Builder $q) use ($term): void {
                $q->where('churches.name', 'like', '%'.$term.'%')
                    ->orWhere('snapshot_units.name', 'like', '%'.$term.'%');
            });
        }

        return $query
            ->orderByDesc('deliveries.available_at')
            ->orderBy('churches.name')
            ->paginate(min(max($perPage, 1), 50), pageName: 'recipientsPage')
            ->through(fn (object $row): OrganizationCommunicationChurchOutcome => $this->mapChurchOutcome($row));
    }

    private function summaryForRevision(OrganizationCommunicationRevision $revision): OrganizationCommunicationTrackingSummary
    {
        $ranked = $this->deduplicatedByChurchQuery($revision->id);

        $counts = DB::query()->fromSub($ranked, 'ranked')
            ->where('ranked.rn', 1)
            ->select('outcome')
            ->selectRaw('count(*) as total')
            ->groupBy('outcome')
            ->pluck('total', 'outcome')
            ->all();

        $declineReasonCounts = DB::query()->fromSub($ranked, 'ranked')
            ->where('ranked.rn', 1)
            ->where('ranked.outcome', OrganizationCommunicationDeliveryOutcomeResolver::DECLINED)
            ->whereNotNull('ranked.decline_reason_code')
            ->select('decline_reason_code')
            ->selectRaw('count(*) as total')
            ->groupBy('decline_reason_code')
            ->pluck('total', 'decline_reason_code')
            ->all();

        $firstDistributedAt = OrganizationCommunicationDistribution::query()
            ->where('organization_communication_revision_id', $revision->id)
            ->whereNotNull('snapshot_at')
            ->min('snapshot_at');

        return new OrganizationCommunicationTrackingSummary(
            revisionId: $revision->id,
            revisionVersion: $revision->version,
            firstDistributedAt: $firstDistributedAt,
            recipientCount: array_sum($counts),
            counts: $counts,
            declineReasonCounts: $declineReasonCounts,
        );
    }

    /**
     * K-ORG-COMMS-001F §21 — ranks each Church's deliveries of this one
     * revision (across every distribution of it) by outcome precedence,
     * via `ROW_NUMBER()`, entirely in SQL. Callers filter `rn = 1` to
     * get exactly one row per Church.
     */
    private function deduplicatedByChurchQuery(int $revisionId): Builder
    {
        $outcomeCase = OrganizationCommunicationDeliveryOutcomeResolver::sqlCaseExpression();

        $base = DB::table('organization_communication_deliveries as deliveries')
            ->leftJoin('organization_communication_imports as imports', 'imports.organization_communication_delivery_id', '=', 'deliveries.id')
            ->where('deliveries.organization_communication_revision_id', $revisionId)
            ->selectRaw('deliveries.id as delivery_id, deliveries.church_id, deliveries.decline_reason_code')
            ->selectRaw("{$outcomeCase} as outcome");

        $rankCase = OrganizationCommunicationDeliveryOutcomeResolver::rankCaseExpression('base.outcome');

        return DB::query()->fromSub($base, 'base')
            ->selectRaw('base.*')
            ->selectRaw("row_number() over (partition by base.church_id order by {$rankCase} asc, base.delivery_id desc) as rn");
    }

    private function recipientRowsQuery(int $distributionId): Builder
    {
        $outcomeCase = OrganizationCommunicationDeliveryOutcomeResolver::sqlCaseExpression();

        return DB::table('organization_communication_deliveries as deliveries')
            ->join('churches', 'churches.id', '=', 'deliveries.church_id')
            ->leftJoin('church_organization_assignments as snapshot_assignments', 'snapshot_assignments.id', '=', 'deliveries.church_organization_assignment_id')
            ->leftJoin('organization_units as snapshot_units', 'snapshot_units.id', '=', 'snapshot_assignments.organization_unit_id')
            ->leftJoin('church_organization_assignments as current_assignments', function ($join): void {
                $join->on('current_assignments.id', '=', 'churches.current_organization_assignment_id')
                    ->on('current_assignments.church_id', '=', 'churches.id');
            })
            ->leftJoin('organization_units as current_units', 'current_units.id', '=', 'current_assignments.organization_unit_id')
            ->leftJoin('organization_communication_imports as imports', 'imports.organization_communication_delivery_id', '=', 'deliveries.id')
            ->where('deliveries.organization_communication_distribution_id', $distributionId)
            ->select([
                'churches.id as church_id', 'churches.name as church_name',
                'snapshot_units.name as snapshot_unit_name',
                'current_units.name as current_unit_name',
                'current_assignments.organization_id as current_assignment_organization_id',
                'current_assignments.status as current_assignment_status',
                'deliveries.organization_id as delivery_organization_id',
                'deliveries.available_at', 'deliveries.accepted_at', 'deliveries.declined_at', 'deliveries.decline_reason_code',
                'imports.imported_at',
            ])
            ->selectRaw("{$outcomeCase} as outcome");
    }

    private function mapChurchOutcome(object $row): OrganizationCommunicationChurchOutcome
    {
        $isDetached = (int) $row->current_assignment_organization_id !== (int) $row->delivery_organization_id
            || $row->current_assignment_status !== ChurchOrganizationAssignmentStatus::ACTIVE->value;

        $respondedAt = $row->declined_at ?? $row->accepted_at;
        $reason = $row->decline_reason_code !== null
            ? OrganizationCommunicationDeclineReasonCode::tryFrom($row->decline_reason_code)?->label()
            : null;

        return new OrganizationCommunicationChurchOutcome(
            churchId: (int) $row->church_id,
            churchName: $row->church_name,
            snapshotUnitPath: $row->snapshot_unit_name ?? '—',
            currentUnitPath: $isDetached ? null : $row->current_unit_name,
            isDetached: $isDetached,
            outcome: $row->outcome,
            outcomeLabel: $this->resolver->label($row->outcome),
            availableAt: $row->available_at,
            respondedAt: $respondedAt,
            declineReasonLabel: $reason,
            importedAt: $row->imported_at,
        );
    }

    private function targetSummary(OrganizationCommunicationDistribution $distribution): string
    {
        return match ($distribution->target_mode) {
            OrganizationCommunicationTargetMode::GOVERNING_SCOPE => 'Entire governing scope',
            OrganizationCommunicationTargetMode::UNIT_SUBTREE => 'Unit: '.($distribution->targetUnit?->name ?? '—'),
            OrganizationCommunicationTargetMode::EXPLICIT_CHURCHES => count($distribution->target_church_ids ?? []).' selected Churches',
        };
    }
}
