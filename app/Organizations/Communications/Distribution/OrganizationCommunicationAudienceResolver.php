<?php

namespace App\Organizations\Communications\Distribution;

use App\Enums\ChurchOrganizationAssignmentStatus;
use App\Enums\OrganizationCommunicationTargetMode;
use App\Models\OrganizationCommunication;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Illuminate\Validation\ValidationException;

/**
 * K-ORG-COMMS-001C §6/§7/§14/§15 — the single query surface behind both
 * the advisory audience preview (HTTP, current-state) and the
 * authoritative snapshot resolution (worker, at materialization start).
 * Both call the same underlying eligibility query — the only difference
 * is *when* it runs, which is exactly the intended preview-vs-snapshot
 * distinction (§15): the query itself never changes, only the moment it
 * is executed changes what it returns.
 *
 * Eligible Church definition (§6), documented decision: an active
 * `ChurchOrganizationAssignment` (via `churches.current_organization_assignment_id`,
 * the same "current assignment" convention `OrganizationWorkspaceQuery`
 * already uses) to a Unit inside the permitted scope, AND
 * `churches.is_active = true`. An operationally inactive Church is not an
 * eligible new-distribution recipient.
 */
final class OrganizationCommunicationAudienceResolver
{
    /**
     * Validates a requested target selection against the communication's
     * governing scope and returns the resolved boundary unit id (the Unit
     * whose subtree eligible Churches are drawn from) plus the bounded,
     * validated explicit Church id list (if any). Throws when the
     * selection would reach outside the governing scope (§71).
     *
     * @param  list<int>|null  $churchIds
     * @return array{unitId: int, churchIds: ?list<int>}
     */
    public function validateTarget(
        OrganizationCommunication $communication,
        OrganizationCommunicationTargetMode $mode,
        ?int $targetUnitId,
        ?array $churchIds,
    ): array {
        $governingUnitId = $communication->governing_unit_id;

        return match ($mode) {
            OrganizationCommunicationTargetMode::GOVERNING_SCOPE => ['unitId' => $governingUnitId, 'churchIds' => null],
            OrganizationCommunicationTargetMode::UNIT_SUBTREE => [
                'unitId' => $this->assertUnitWithinGoverningScope($communication, $targetUnitId),
                'churchIds' => null,
            ],
            OrganizationCommunicationTargetMode::EXPLICIT_CHURCHES => [
                'unitId' => $governingUnitId,
                'churchIds' => $this->assertBoundedChurchSelection($churchIds),
            ],
        };
    }

    private function assertUnitWithinGoverningScope(OrganizationCommunication $communication, ?int $targetUnitId): int
    {
        if ($targetUnitId === null) {
            throw ValidationException::withMessages(['target_unit_id' => 'Choose a Unit within the governing scope.']);
        }

        $withinScope = DB::table('organization_unit_paths')
            ->where('organization_id', $communication->organization_id)
            ->where('ancestor_id', $communication->governing_unit_id)
            ->where('descendant_id', $targetUnitId)
            ->exists();

        if (! $withinScope) {
            throw ValidationException::withMessages(['target_unit_id' => 'The selected Unit is outside this communication\'s governing scope.']);
        }

        return $targetUnitId;
    }

    /** @param list<int>|null $churchIds @return list<int> */
    private function assertBoundedChurchSelection(?array $churchIds): array
    {
        $churchIds = array_values(array_unique(array_map('intval', $churchIds ?? [])));
        $max = (int) config('organization-communications.max_explicit_target_churches');

        if ($churchIds === []) {
            throw ValidationException::withMessages(['church_ids' => 'Select at least one Church.']);
        }

        if (count($churchIds) > $max) {
            throw ValidationException::withMessages(['church_ids' => "Explicit Church selection is limited to {$max} Churches. Use scope or Unit targeting for larger audiences."]);
        }

        return $churchIds;
    }

    /**
     * Advisory, current-state preview (§14/§15) — paginated, searchable.
     * Not historical evidence.
     */
    public function preview(
        int $organizationId,
        int $unitId,
        ?array $churchIds,
        string $search = '',
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = $this->eligibleChurchesQuery($organizationId, $unitId, $churchIds)
            ->select(['c.id', 'c.name', 'c.slug', 'ou.name as unit_name']);

        $term = mb_strtolower(trim($search));
        if ($term !== '') {
            $query->whereRaw('lower(c.name) like ?', ['%'.$term.'%']);
        }

        return $query->orderBy('c.name')->paginate(min(max($perPage, 1), 50), pageName: 'audiencePage');
    }

    public function previewCount(int $organizationId, int $unitId, ?array $churchIds): int
    {
        return $this->eligibleChurchesQuery($organizationId, $unitId, $churchIds)->count();
    }

    /**
     * Authoritative snapshot resolution (§16/§17) — called only from
     * inside the worker's materialization transaction. Lazily cursors
     * results rather than loading the whole audience into memory (§24).
     *
     * @return LazyCollection<int, object{church_id: int, assignment_id: int}>
     */
    public function resolveForSnapshot(int $organizationId, int $unitId, ?array $churchIds): LazyCollection
    {
        return $this->eligibleChurchesQuery($organizationId, $unitId, $churchIds)
            ->select(['c.id as church_id', 'coa.id as assignment_id'])
            ->orderBy('c.id')
            ->cursor()
            ->map(fn (object $row): object => $row);
    }

    /** @param list<int>|null $churchIds */
    private function eligibleChurchesQuery(int $organizationId, int $unitId, ?array $churchIds): Builder
    {
        $unitIds = DB::table('organization_unit_paths')
            ->where('organization_id', $organizationId)
            ->where('ancestor_id', $unitId)
            ->select('descendant_id');

        $query = DB::table('churches as c')
            ->join('church_organization_assignments as coa', function ($join): void {
                $join->on('coa.id', '=', 'c.current_organization_assignment_id')
                    ->on('coa.church_id', '=', 'c.id')
                    ->where('coa.status', ChurchOrganizationAssignmentStatus::ACTIVE->value);
            })
            ->join('organization_units as ou', 'ou.id', '=', 'coa.organization_unit_id')
            ->where('coa.organization_id', $organizationId)
            ->where('c.is_active', true)
            ->whereIn('coa.organization_unit_id', $unitIds);

        if ($churchIds !== null) {
            $query->whereIn('c.id', $churchIds);
        }

        return $query;
    }
}
