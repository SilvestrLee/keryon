<?php

namespace App\Organizations\Read;

use App\Enums\ChurchOnboardingStatus;
use App\Enums\DomainStatus;
use App\Enums\DomainTlsStatus;
use App\Enums\OrganizationAuditEventType;
use App\Enums\OrganizationAuditSubjectType;
use App\Enums\OrganizationCapability;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationRoleAssignmentStatus;
use App\Enums\OrganizationUnitStatus;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Organizations\Communications\Tracking\OrganizationCommunicationAttentionSignals;
use App\Organizations\OrganizationScopeResolver;
use App\Organizations\Read\Dto\OrganizationChurchSummary;
use App\Organizations\Read\Dto\OrganizationDashboardSnapshot;
use App\Organizations\Read\Dto\OrganizationUnitSummary;
use App\Support\OrganizationContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class OrganizationWorkspaceQuery
{
    public function __construct(
        private readonly OrganizationContext $context,
        private readonly OrganizationScopeResolver $scope,
        private readonly OrganizationCommunicationAttentionSignals $communicationAttention,
    ) {}

    public function dashboard(): OrganizationDashboardSnapshot
    {
        $organization = $this->organization();
        $canViewUnits = $this->scope->hasOrganizationCapability(OrganizationCapability::UnitsView);
        $canViewChurches = $this->scope->hasOrganizationCapability(OrganizationCapability::ChurchesView);
        $unitCount = $canViewUnits
            ? $this->scope->unitsInScope()->whereKeyNot($organization->root_unit_id)->count()
            : 0;
        $metrics = $canViewChurches ? $this->churchMetrics() : $this->emptyChurchMetrics();
        $attention = $canViewChurches
            ? $this->applyAttention($this->churchRows())->orderBy('c.name')->limit(6)->get()->map(fn (object $row): OrganizationChurchSummary => $this->mapChurch($row))->all()
            : [];
        $churchPreview = $canViewChurches
            ? $this->churchRows()->orderBy('c.name')->limit(5)->get()->map(fn (object $row): OrganizationChurchSummary => $this->mapChurch($row))->all()
            : [];
        $unitPreview = $canViewUnits
            ? $this->unitRows()->whereNotNull('ou.parent_id')->where('ou.status', OrganizationUnitStatus::ACTIVE->value)->orderBy('ou.name')->limit(5)->get()->map(fn (object $row): OrganizationUnitSummary => $this->mapUnit($row))->all()
            : [];
        $peopleCount = $this->scope->hasRootCapability(OrganizationCapability::MembershipsView)
            ? $organization->memberships()->where('status', OrganizationMembershipStatus::ACTIVE->value)->count()
            : null;

        return new OrganizationDashboardSnapshot(
            organizationName: $organization->name,
            organizationState: str($organization->status->value)->headline()->toString(),
            scopes: $this->scopePresentation(),
            unitCount: $unitCount,
            churchCount: $metrics['church_count'],
            activeChurchCount: $metrics['active_church_count'],
            attentionCount: $metrics['attention_count'],
            onboardingAttentionCount: $metrics['onboarding_attention_count'],
            websiteLiveCount: $metrics['website_live_count'],
            websiteAttentionCount: $metrics['website_attention_count'],
            domainConnectedCount: $metrics['domain_connected_count'],
            domainAttentionCount: $metrics['domain_attention_count'],
            keryonAddressCount: $metrics['keryon_address_count'],
            peopleCount: $peopleCount,
            attentionItems: $attention,
            churchPreview: $churchPreview,
            unitPreview: $unitPreview,
            communicationItems: $this->communicationAttention->forDashboard(),
            campaignItems: [],
            recentActivity: $this->recentActivity(),
        );
    }

    /** @return list<array{path:string,responsibilities:list<string>}> */
    public function scopePresentation(): array
    {
        $membership = $this->membership();
        $organization = $this->organization();
        $assignments = $membership->roleAssignments()
            ->where('status', OrganizationRoleAssignmentStatus::ACTIVE->value)
            ->with(['unit.type'])
            ->get()
            ->filter(fn ($assignment): bool => $assignment->unit?->status === OrganizationUnitStatus::ACTIVE)
            ->groupBy('organization_unit_id');

        if ($assignments->isEmpty()) {
            return [];
        }

        $unitIds = $assignments->keys()->map(fn ($id): int => (int) $id)->all();
        $paths = DB::table('organization_unit_paths as paths')
            ->join('organization_units as ancestors', 'ancestors.id', '=', 'paths.ancestor_id')
            ->where('paths.organization_id', $organization->id)
            ->whereIn('paths.descendant_id', $unitIds)
            ->orderBy('paths.descendant_id')
            ->orderByDesc('paths.depth')
            ->get(['paths.descendant_id', 'ancestors.name'])
            ->groupBy('descendant_id');

        return $assignments->map(function (Collection $unitAssignments, int|string $unitId) use ($organization, $paths): array {
            $path = (int) $unitId === $organization->root_unit_id
                ? 'Entire organization'
                : $paths->get($unitId, collect())->pluck('name')->implode(' → ');
            $responsibilities = $unitAssignments
                ->pluck('role')
                ->map(fn (OrganizationRole $role): string => $role->label())
                ->unique()
                ->values()
                ->all();

            return compact('path', 'responsibilities');
        })->values()->all();
    }

    public function paginateChurches(string $search = '', string $attention = '', int $perPage = 18): LengthAwarePaginator
    {
        $query = $this->churchRows();
        $term = mb_strtolower(trim($search));
        if ($term !== '') {
            $query->where(function (Builder $nested) use ($term): void {
                $nested->whereRaw('lower(c.name) like ?', ['%'.$term.'%'])
                    ->orWhereRaw('lower(ou.name) like ?', ['%'.$term.'%']);
            });
        }
        if ($attention === 'needs_attention') {
            $this->applyAttention($query);
        } elseif ($attention === 'current') {
            $this->applyNoAttention($query);
        }

        return $query
            ->orderBy('c.name')
            ->paginate(min(max($perPage, 1), 50), pageName: 'churchesPage')
            ->through(fn (object $row): OrganizationChurchSummary => $this->mapChurch($row));
    }

    public function findChurch(int $id): OrganizationChurchSummary
    {
        $row = $this->churchRows()->where('c.id', $id)->first() ?? abort(404);

        return $this->mapChurch($row, $this->unitPath((int) $row->unit_id));
    }

    public function paginateUnits(string $search = '', string $state = 'active', int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->unitRows();
        $term = mb_strtolower(trim($search));
        if ($term !== '') {
            $query->where(function (Builder $nested) use ($term): void {
                $nested->whereRaw('lower(ou.name) like ?', ['%'.$term.'%'])
                    ->orWhereRaw('lower(ou.code) like ?', ['%'.$term.'%'])
                    ->orWhereRaw('lower(parent.name) like ?', ['%'.$term.'%']);
            });
        }
        if (in_array($state, [OrganizationUnitStatus::ACTIVE->value, OrganizationUnitStatus::ARCHIVED->value], true)) {
            $query->where('ou.status', $state);
        }

        return $query
            ->orderByRaw('case when ou.parent_id is null then 0 else 1 end')
            ->orderBy('ou.name')
            ->paginate(min(max($perPage, 1), 50), pageName: 'unitsPage')
            ->through(fn (object $row): OrganizationUnitSummary => $this->mapUnit($row));
    }

    /** @return list<OrganizationUnitSummary> */
    public function scopeRoots(): array
    {
        $ids = $this->membership()->roleAssignments()
            ->where('status', OrganizationRoleAssignmentStatus::ACTIVE->value)
            ->pluck('organization_unit_id')
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return $this->unitRows()
            ->whereIn('ou.id', $ids)
            ->orderBy('ou.name')
            ->get()
            ->map(fn (object $row): OrganizationUnitSummary => $this->mapUnit($row, $this->unitPath((int) $row->id)))
            ->all();
    }

    public function findUnit(int $id): OrganizationUnitSummary
    {
        $row = $this->unitRows()->where('ou.id', $id)->first() ?? abort(404);

        return $this->mapUnit($row, $this->unitPath($id));
    }

    public function paginateChildUnits(int $parentId, int $perPage = 12): LengthAwarePaginator
    {
        $this->findUnit($parentId);

        return $this->unitRows()
            ->where('ou.parent_id', $parentId)
            ->where('ou.status', OrganizationUnitStatus::ACTIVE->value)
            ->orderBy('ou.name')
            ->paginate(min(max($perPage, 1), 50), pageName: 'childrenPage')
            ->through(fn (object $row): OrganizationUnitSummary => $this->mapUnit($row));
    }

    public function paginateChurchesBelowUnit(int $unitId, int $perPage = 10): LengthAwarePaginator
    {
        $this->findUnit($unitId);
        $descendants = DB::table('organization_unit_paths')
            ->where('organization_id', $this->organization()->id)
            ->where('ancestor_id', $unitId)
            ->select('descendant_id');

        return $this->churchRows()
            ->whereIn('coa.organization_unit_id', $descendants)
            ->orderBy('c.name')
            ->paginate(min(max($perPage, 1), 50), pageName: 'unitChurchesPage')
            ->through(fn (object $row): OrganizationChurchSummary => $this->mapChurch($row));
    }

    private function churchRows(OrganizationCapability $capability = OrganizationCapability::ChurchesView): Builder
    {
        abort_unless($this->scope->hasOrganizationCapability($capability), 403);
        $scopedChurches = $this->scope->churchesInScope($capability)->select('churches.id');
        $manageableChurches = $this->scope->churchesInScope(OrganizationCapability::ChurchesManageAssignments)->select('churches.id');
        $primaryDomains = DB::table('church_domains')
            ->where('is_primary', true)
            ->whereNull('released_at')
            ->groupBy('church_id')
            ->selectRaw('church_id, max(id) as domain_id');

        return DB::table('churches as c')
            ->join('church_organization_assignments as coa', function ($join): void {
                $join->on('coa.id', '=', 'c.current_organization_assignment_id')
                    ->on('coa.church_id', '=', 'c.id')
                    ->where('coa.status', 'active');
            })
            ->join('organization_units as ou', 'ou.id', '=', 'coa.organization_unit_id')
            ->join('organization_unit_types as out', 'out.id', '=', 'ou.organization_unit_type_id')
            ->leftJoin('church_onboarding_states as cos', 'cos.church_id', '=', 'c.id')
            ->leftJoin('website_settings as ws', 'ws.church_id', '=', 'c.id')
            ->leftJoinSub($primaryDomains, 'primary_domains', 'primary_domains.church_id', '=', 'c.id')
            ->leftJoin('church_domains as cd', 'cd.id', '=', 'primary_domains.domain_id')
            ->whereIn('c.id', $scopedChurches)
            ->select([
                'c.id', 'c.name', 'c.slug', 'c.is_active',
                'coa.id as assignment_id', 'coa.organization_unit_id as unit_id',
                'ou.name as unit_name', 'out.label as unit_type',
                'cos.status as onboarding_status', 'cos.current_step as onboarding_step',
                'ws.current_publication_id',
                'cd.id as domain_id', 'cd.status as domain_status', 'cd.tls_status as domain_tls_status',
                'cd.ownership_verified_at', 'cd.routing_verified_at', 'cd.tls_ready_at', 'cd.disabled_at',
            ])
            ->selectSub(
                DB::query()->fromSub($manageableChurches, 'manageable_churches')
                    ->selectRaw('count(*)')
                    ->whereColumn('manageable_churches.id', 'c.id'),
                'can_manage',
            );
    }

    private function unitRows(): Builder
    {
        abort_unless($this->scope->hasOrganizationCapability(OrganizationCapability::UnitsView), 403);
        $scopedUnits = $this->scope->unitsInScope(includeArchived: true)->select('organization_units.id');
        $manageableUnits = $this->scope->unitsInScope(OrganizationCapability::UnitsManage)->select('organization_units.id');

        return DB::table('organization_units as ou')
            ->join('organization_unit_types as out', 'out.id', '=', 'ou.organization_unit_type_id')
            ->leftJoin('organization_units as parent', 'parent.id', '=', 'ou.parent_id')
            ->whereIn('ou.id', $scopedUnits)
            ->select(['ou.id', 'ou.name', 'ou.code', 'ou.status', 'ou.parent_id', 'out.label as unit_type', 'parent.name as parent_name'])
            ->selectSub(
                DB::table('organization_units as children')
                    ->selectRaw('count(*)')
                    ->whereColumn('children.parent_id', 'ou.id')
                    ->where('children.status', OrganizationUnitStatus::ACTIVE->value),
                'child_unit_count',
            )
            ->selectSub(
                DB::table('church_organization_assignments as unit_churches')
                    ->selectRaw('count(*)')
                    ->whereColumn('unit_churches.organization_unit_id', 'ou.id')
                    ->where('unit_churches.status', 'active'),
                'direct_church_count',
            )
            ->selectSub(
                DB::query()->fromSub($manageableUnits, 'manageable_units')
                    ->selectRaw('count(*)')
                    ->whereColumn('manageable_units.id', 'ou.id'),
                'can_manage',
            );
    }

    /** @return array{church_count:int,active_church_count:int,attention_count:int,onboarding_attention_count:int,website_live_count:int,website_attention_count:int,domain_connected_count:int,domain_attention_count:int,keryon_address_count:int} */
    private function churchMetrics(): array
    {
        $row = DB::query()->fromSub($this->churchRows(), 'church_scope')->selectRaw(
            'count(*) as church_count,
            coalesce(sum(case when is_active = 1 then 1 else 0 end), 0) as active_church_count,
            coalesce(sum(case when is_active = 0 or onboarding_status in (?, ?) or current_publication_id is null or (domain_id is not null and not (domain_status = ? and domain_tls_status = ? and ownership_verified_at is not null and routing_verified_at is not null and tls_ready_at is not null and disabled_at is null)) then 1 else 0 end), 0) as attention_count,
            coalesce(sum(case when onboarding_status in (?, ?) then 1 else 0 end), 0) as onboarding_attention_count,
            coalesce(sum(case when current_publication_id is not null then 1 else 0 end), 0) as website_live_count,
            coalesce(sum(case when current_publication_id is null then 1 else 0 end), 0) as website_attention_count,
            coalesce(sum(case when domain_id is not null and domain_status = ? and domain_tls_status = ? and ownership_verified_at is not null and routing_verified_at is not null and tls_ready_at is not null and disabled_at is null then 1 else 0 end), 0) as domain_connected_count,
            coalesce(sum(case when domain_id is not null and not (domain_status = ? and domain_tls_status = ? and ownership_verified_at is not null and routing_verified_at is not null and tls_ready_at is not null and disabled_at is null) then 1 else 0 end), 0) as domain_attention_count,
            coalesce(sum(case when domain_id is null then 1 else 0 end), 0) as keryon_address_count',
            [
                ChurchOnboardingStatus::NOT_STARTED->value, ChurchOnboardingStatus::IN_PROGRESS->value,
                DomainStatus::Active->value, DomainTlsStatus::Ready->value,
                ChurchOnboardingStatus::NOT_STARTED->value, ChurchOnboardingStatus::IN_PROGRESS->value,
                DomainStatus::Active->value, DomainTlsStatus::Ready->value,
                DomainStatus::Active->value, DomainTlsStatus::Ready->value,
            ],
        )->first();

        return collect((array) $row)->map(fn ($value): int => (int) $value)->all();
    }

    /** @return array{church_count:int,active_church_count:int,attention_count:int,onboarding_attention_count:int,website_live_count:int,website_attention_count:int,domain_connected_count:int,domain_attention_count:int,keryon_address_count:int} */
    private function emptyChurchMetrics(): array
    {
        return [
            'church_count' => 0,
            'active_church_count' => 0,
            'attention_count' => 0,
            'onboarding_attention_count' => 0,
            'website_live_count' => 0,
            'website_attention_count' => 0,
            'domain_connected_count' => 0,
            'domain_attention_count' => 0,
            'keryon_address_count' => 0,
        ];
    }

    private function applyAttention(Builder $query): Builder
    {
        return $query->where(function (Builder $attention): void {
            $attention->where('c.is_active', false)
                ->orWhereIn('cos.status', [ChurchOnboardingStatus::NOT_STARTED->value, ChurchOnboardingStatus::IN_PROGRESS->value])
                ->orWhereNull('ws.current_publication_id')
                ->orWhere(function (Builder $domain): void {
                    $domain->whereNotNull('cd.id')->where(function (Builder $notReady): void {
                        $notReady->where('cd.status', '!=', DomainStatus::Active->value)
                            ->orWhere('cd.tls_status', '!=', DomainTlsStatus::Ready->value)
                            ->orWhereNull('cd.ownership_verified_at')
                            ->orWhereNull('cd.routing_verified_at')
                            ->orWhereNull('cd.tls_ready_at')
                            ->orWhereNotNull('cd.disabled_at');
                    });
                });
        });
    }

    private function applyNoAttention(Builder $query): Builder
    {
        return $query
            ->where('c.is_active', true)
            ->where(function (Builder $onboarding): void {
                $onboarding->whereNull('cos.status')
                    ->orWhereNotIn('cos.status', [ChurchOnboardingStatus::NOT_STARTED->value, ChurchOnboardingStatus::IN_PROGRESS->value]);
            })
            ->whereNotNull('ws.current_publication_id')
            ->where(function (Builder $domain): void {
                $domain->whereNull('cd.id')->orWhere(function (Builder $ready): void {
                    $ready->where('cd.status', DomainStatus::Active->value)
                        ->where('cd.tls_status', DomainTlsStatus::Ready->value)
                        ->whereNotNull('cd.ownership_verified_at')
                        ->whereNotNull('cd.routing_verified_at')
                        ->whereNotNull('cd.tls_ready_at')
                        ->whereNull('cd.disabled_at');
                });
            });
    }

    private function mapChurch(object $row, ?string $unitPath = null): OrganizationChurchSummary
    {
        $domainConnected = $row->domain_id !== null
            && $row->domain_status === DomainStatus::Active->value
            && $row->domain_tls_status === DomainTlsStatus::Ready->value
            && $row->ownership_verified_at !== null
            && $row->routing_verified_at !== null
            && $row->tls_ready_at !== null
            && $row->disabled_at === null;
        $onboardingState = match ($row->onboarding_status) {
            ChurchOnboardingStatus::NOT_STARTED->value => 'Not started',
            ChurchOnboardingStatus::IN_PROGRESS->value => 'In progress',
            ChurchOnboardingStatus::COMPLETED->value => 'Complete',
            ChurchOnboardingStatus::DISMISSED->value => 'Dismissed',
            default => 'Not recorded',
        };
        $domainState = match (true) {
            $row->domain_id === null => 'Keryon address',
            $domainConnected => 'Custom domain active',
            $row->domain_status === DomainStatus::Disabled->value => 'Domain disabled',
            in_array($row->domain_status, [DomainStatus::PendingVerification->value, DomainStatus::Verified->value], true) => 'Verification required',
            default => 'Domain needs attention',
        };
        $attention = [];
        if (! (bool) $row->is_active) {
            $attention[] = 'Church is inactive';
        }
        if (in_array($row->onboarding_status, [ChurchOnboardingStatus::NOT_STARTED->value, ChurchOnboardingStatus::IN_PROGRESS->value], true)) {
            $attention[] = 'Onboarding is incomplete';
        }
        if ($row->current_publication_id === null) {
            $attention[] = 'Website is not published';
        }
        if ($row->domain_id !== null && ! $domainConnected) {
            $attention[] = $domainState;
        }

        return new OrganizationChurchSummary(
            id: (int) $row->id,
            assignmentId: (int) $row->assignment_id,
            unitId: (int) $row->unit_id,
            name: $row->name,
            slug: $row->slug,
            unitName: $row->unit_name,
            unitType: $row->unit_type,
            unitPath: $unitPath ?? $row->unit_name,
            churchState: (bool) $row->is_active ? 'Active' : 'Inactive',
            onboardingState: $onboardingState,
            websiteState: $row->current_publication_id === null ? 'Not published' : 'Live',
            domainState: $domainState,
            attention: $attention,
            canManage: (int) $row->can_manage > 0,
        );
    }

    private function mapUnit(object $row, ?string $path = null): OrganizationUnitSummary
    {
        return new OrganizationUnitSummary(
            id: (int) $row->id,
            name: $row->name,
            type: $row->unit_type,
            code: $row->code,
            state: str($row->status)->headline()->toString(),
            parentName: $row->parent_name,
            path: $path ?? ($row->parent_name === null ? $row->name : $row->parent_name.' → '.$row->name),
            childUnitCount: (int) $row->child_unit_count,
            directChurchCount: (int) $row->direct_church_count,
            canManage: (int) $row->can_manage > 0,
        );
    }

    private function unitPath(int $unitId): string
    {
        return DB::table('organization_unit_paths as paths')
            ->join('organization_units as ancestors', 'ancestors.id', '=', 'paths.ancestor_id')
            ->where('paths.organization_id', $this->organization()->id)
            ->where('paths.descendant_id', $unitId)
            ->orderByDesc('paths.depth')
            ->pluck('ancestors.name')
            ->implode(' → ');
    }

    /** @return list<array{label:string,occurred_at:string}> */
    private function recentActivity(): array
    {
        $organization = $this->organization();
        $query = DB::table('organization_audit_events')
            ->where('organization_id', $organization->id);

        if (! $this->scope->hasRootCapability(OrganizationCapability::OrganizationView)) {
            $unitIds = $this->scope->unitsInScope(OrganizationCapability::UnitsView, includeArchived: true)
                ->select('organization_units.id');
            $query->whereIn('organization_unit_id', $unitIds)
                ->whereIn('subject_type', [
                    OrganizationAuditSubjectType::UNIT->value,
                    OrganizationAuditSubjectType::CHURCH_ASSIGNMENT->value,
                ]);
        }

        return $query->latest('occurred_at')->limit(6)->get(['event_type', 'occurred_at'])
            ->map(fn (object $event): array => [
                'label' => $this->activityLabel($event->event_type),
                'occurred_at' => (string) $event->occurred_at,
            ])->all();
    }

    private function activityLabel(string $event): string
    {
        return match (OrganizationAuditEventType::tryFrom($event)) {
            OrganizationAuditEventType::UNIT_CREATED => 'Unit created',
            OrganizationAuditEventType::UNIT_MOVED => 'Unit moved',
            OrganizationAuditEventType::UNIT_ARCHIVED => 'Unit archived',
            OrganizationAuditEventType::ATTACHMENT_REQUESTED => 'Church attachment requested',
            OrganizationAuditEventType::ATTACHMENT_ACCEPTED => 'Church attachment accepted',
            OrganizationAuditEventType::ATTACHMENT_REJECTED => 'Church attachment rejected',
            OrganizationAuditEventType::CHURCH_MOVED => 'Church moved to another Unit',
            OrganizationAuditEventType::CHURCH_DETACHED => 'Church detached',
            OrganizationAuditEventType::MEMBERSHIP_INVITED => 'Organization member invited',
            OrganizationAuditEventType::MEMBERSHIP_ACTIVATED => 'Organization membership activated',
            OrganizationAuditEventType::MEMBERSHIP_SUSPENDED => 'Organization membership suspended',
            OrganizationAuditEventType::MEMBERSHIP_REMOVED => 'Organization membership removed',
            OrganizationAuditEventType::ROLE_ASSIGNED => 'Responsibility assigned',
            OrganizationAuditEventType::ROLE_SUSPENDED => 'Responsibility suspended',
            OrganizationAuditEventType::ROLE_REMOVED => 'Responsibility removed',
            OrganizationAuditEventType::ROLE_SCOPE_CHANGED => 'Responsibility scope changed',
            OrganizationAuditEventType::ORGANIZATION_CREATED => 'Organization created',
            default => 'Organization activity recorded',
        };
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
