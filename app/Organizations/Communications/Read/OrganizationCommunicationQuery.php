<?php

namespace App\Organizations\Communications\Read;

use App\Enums\OrganizationAuditEventType;
use App\Enums\OrganizationAuditSubjectType;
use App\Enums\OrganizationCapability;
use App\Enums\OrganizationCommunicationKind;
use App\Enums\OrganizationCommunicationRevisionState;
use App\Enums\OrganizationCommunicationState;
use App\Enums\OrganizationMembershipStatus;
use App\Models\Organization;
use App\Models\OrganizationAuditEvent;
use App\Models\OrganizationCommunication;
use App\Models\OrganizationCommunicationAsset;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Organizations\Communications\Read\Dto\OrganizationCommunicationSummary;
use App\Organizations\OrganizationScopeResolver;
use App\Support\OrganizationContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * K-ORG-COMMS-001B §66-§67 — the focused read model behind the
 * Communications landing/detail surfaces. Every query is scoped through
 * `OrganizationScopeResolver` (never a raw submitted Unit/Organization
 * id) and stays inside the Organization communication domain only — no
 * Church-owned table is ever joined or selected here (§65).
 */
final class OrganizationCommunicationQuery
{
    public function __construct(
        private readonly OrganizationContext $context,
        private readonly OrganizationScopeResolver $scope,
    ) {}

    public function paginate(string $kind = '', string $state = '', string $search = '', int $perPage = 12): LengthAwarePaginator
    {
        $query = $this->scopedQuery();

        if (in_array($kind, ['communication', 'campaign'], true)) {
            $query->where('kind', $kind);
        }

        if ($state !== '') {
            $this->applyStateFilter($query, $state);
        }

        $term = trim($search);
        if ($term !== '') {
            $query->whereHas('currentRevision', fn (Builder $revision) => $revision->where('title', 'like', '%'.$term.'%'));
        }

        return $query
            ->orderByDesc('updated_at')
            ->paginate(min(max($perPage, 1), 50), pageName: 'communicationsPage')
            ->through(fn (OrganizationCommunication $communication): OrganizationCommunicationSummary => $this->summarize($communication));
    }

    /** @return array{awaiting_review: int, changes_requested: int} */
    public function attention(): array
    {
        $approvableUnitIds = $this->scope->unitsInScope(OrganizationCapability::CommunicationsApprove, includeArchived: true)->select('organization_units.id');
        $editableUnitIds = $this->scope->unitsInScope(OrganizationCapability::CommunicationsEdit, includeArchived: true)->select('organization_units.id');

        $awaitingReview = OrganizationCommunication::query()
            ->where('organization_id', $this->organization()->id)
            ->whereIn('governing_unit_id', $approvableUnitIds)
            ->whereHas('currentRevision', fn (Builder $revision) => $revision->where('state', OrganizationCommunicationRevisionState::IN_REVIEW->value))
            ->count();

        $changesRequested = OrganizationCommunication::query()
            ->where('organization_id', $this->organization()->id)
            ->whereIn('governing_unit_id', $editableUnitIds)
            ->whereHas('currentRevision', fn (Builder $revision) => $revision->where('state', OrganizationCommunicationRevisionState::CHANGES_REQUESTED->value))
            ->count();

        return ['awaiting_review' => $awaitingReview, 'changes_requested' => $changesRequested];
    }

    public function find(int $id): OrganizationCommunication
    {
        return $this->scopedQuery()
            ->with([
                'revisions.materials',
                'revisions.assets',
                'revisions.creatorMembership.user',
                'revisions.approverMembership.user',
            ])
            ->findOrFail($id);
    }

    /** @return array<int, string> */
    public function governingUnitOptions(): array
    {
        return $this->scope->unitsInScope(OrganizationCapability::CommunicationsCreate)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn ($unit) => [$unit->id => $this->unitPath((int) $unit->id, $unit->name)])
            ->all();
    }

    public function unitPath(int $unitId, ?string $fallbackName = null): string
    {
        $organization = $this->organization();
        if ($unitId === $organization->root_unit_id) {
            return 'Entire organization';
        }

        $path = DB::table('organization_unit_paths as paths')
            ->join('organization_units as ancestors', 'ancestors.id', '=', 'paths.ancestor_id')
            ->where('paths.organization_id', $organization->id)
            ->where('paths.descendant_id', $unitId)
            ->orderByDesc('paths.depth')
            ->pluck('ancestors.name')
            ->implode(' → ');

        return $path !== '' ? $path : (string) $fallbackName;
    }

    public function summarize(OrganizationCommunication $communication): OrganizationCommunicationSummary
    {
        $revision = $communication->currentRevision;
        $displayState = $communication->state === OrganizationCommunicationState::CLOSED
            ? 'closed'
            : ($revision?->state->value ?? OrganizationCommunicationRevisionState::DRAFT->value);

        return new OrganizationCommunicationSummary(
            id: $communication->id,
            uuid: $communication->uuid,
            kind: $communication->kind->value,
            kindLabel: $communication->kind === OrganizationCommunicationKind::CAMPAIGN ? 'Shared campaign' : 'Communication',
            title: $revision?->title ?? 'Untitled communication',
            displayState: $displayState,
            displayStateLabel: $this->stateLabel($displayState),
            governingUnitName: $communication->governingUnit?->name ?? '—',
            governingUnitPath: $this->unitPath($communication->governing_unit_id, $communication->governingUnit?->name),
            revisionVersion: $revision?->version ?? 1,
            materialsCount: $revision?->materials?->count() ?? 0,
            assetsCount: $revision?->assets?->count() ?? 0,
            updatedAt: (string) $communication->updated_at,
            canEdit: (bool) auth()->user()?->can('update', $communication),
            canApprove: (bool) auth()->user()?->can('approve', $communication),
        );
    }

    public function stateLabel(string $state): string
    {
        return match ($state) {
            'draft' => 'Draft',
            'in_review' => 'In review',
            'changes_requested' => 'Changes requested',
            'approved' => 'Approved',
            'distributed' => 'Approved',
            'closed' => 'Closed',
            default => str($state)->headline()->toString(),
        };
    }

    /**
     * K-ORG-COMMS-001B §33 — bounded, readable governance history for one
     * communication. No raw JSON is ever exposed to the view.
     *
     * @return Collection<int, array{label: string, occurred_at: string, actor: ?string}>
     */
    public function auditEvents(OrganizationCommunication $communication): Collection
    {
        $revisionIds = $communication->revisions->pluck('id');
        $assetIds = OrganizationCommunicationAsset::withTrashed()
            ->where('organization_communication_id', $communication->id)
            ->pluck('id');

        $events = OrganizationAuditEvent::query()
            ->where('organization_id', $communication->organization_id)
            ->where(function (Builder $scope) use ($communication, $revisionIds, $assetIds): void {
                $scope->where(fn (Builder $q) => $q->where('subject_type', OrganizationAuditSubjectType::COMMUNICATION->value)->where('subject_id', $communication->id))
                    ->orWhere(fn (Builder $q) => $q->where('subject_type', OrganizationAuditSubjectType::COMMUNICATION_REVISION->value)->whereIn('subject_id', $revisionIds))
                    ->orWhere(fn (Builder $q) => $q->where('subject_type', OrganizationAuditSubjectType::COMMUNICATION_ASSET->value)->whereIn('subject_id', $assetIds));
            })
            ->latest('occurred_at')
            ->limit(30)
            ->get();

        $actorNames = User::query()
            ->whereIn('id', $events->pluck('actor_user_id')->filter()->unique())
            ->pluck('name', 'id');

        return $events->map(fn (OrganizationAuditEvent $event): array => [
            'label' => $this->auditLabel($event->event_type),
            'occurred_at' => (string) $event->occurred_at,
            'actor' => $actorNames->get($event->actor_user_id),
        ]);
    }

    private function auditLabel(OrganizationAuditEventType $event): string
    {
        return match ($event) {
            OrganizationAuditEventType::COMMUNICATION_CREATED => 'Communication created',
            OrganizationAuditEventType::COMMUNICATION_REVISION_CREATED => 'New Draft revision created',
            OrganizationAuditEventType::COMMUNICATION_REVISION_SUBMITTED => 'Submitted for review',
            OrganizationAuditEventType::COMMUNICATION_REVISION_CHANGES_REQUESTED => 'Changes requested',
            OrganizationAuditEventType::COMMUNICATION_REVISION_APPROVED => 'Revision approved',
            OrganizationAuditEventType::COMMUNICATION_WITHDRAWN => 'Communication withdrawn',
            OrganizationAuditEventType::COMMUNICATION_CLOSED => 'Communication closed',
            OrganizationAuditEventType::COMMUNICATION_ASSET_ADDED => 'Asset added',
            OrganizationAuditEventType::COMMUNICATION_ASSET_REMOVED => 'Asset removed',
            default => 'Communication activity recorded',
        };
    }

    private function applyStateFilter(Builder $query, string $state): void
    {
        if ($state === 'closed') {
            $query->where('state', OrganizationCommunicationState::CLOSED->value);

            return;
        }

        $query->where('state', '!=', OrganizationCommunicationState::CLOSED->value)
            ->whereHas('currentRevision', fn (Builder $revision) => $revision->where('state', $state));
    }

    private function scopedQuery(): Builder
    {
        $unitIds = $this->scope->unitsInScope(OrganizationCapability::CommunicationsView, includeArchived: true)->select('organization_units.id');

        return OrganizationCommunication::query()
            ->where('organization_id', $this->organization()->id)
            ->whereIn('governing_unit_id', $unitIds)
            ->with(['governingUnit', 'currentRevision.materials', 'currentRevision.assets']);
    }

    private function membership(): OrganizationMembership
    {
        $membership = $this->context->currentMembership();
        abort_unless($membership?->status === OrganizationMembershipStatus::ACTIVE, 403);

        return $membership;
    }

    private function organization(): Organization
    {
        return $this->membership()->organization ?? abort(403);
    }
}
