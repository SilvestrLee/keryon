<?php

namespace App\Platform\Read;

use App\Enums\PlatformCapability;
use App\Platform\Read\Dto\PlatformOrganizationSummary;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class PlatformOrganizationQuery extends PlatformReadQuery
{
    public function paginate(string $search = '', string $status = '', int $perPage = 20): LengthAwarePaginator
    {
        $this->authorize(PlatformCapability::OrganizationsView);
        $q = $this->base();
        $term = mb_strtolower(trim($search));
        if ($term !== '') {
            $q->where(fn (Builder $n) => $n->whereRaw('lower(o.name) like ?', [$term.'%'])->orWhereRaw('lower(o.slug) like ?', [$term.'%'])->orWhereRaw('lower(o.uuid) like ?', [$term.'%']));
        }
        if ($status !== '') {
            $q->where('o.status', $status);
        }

        return $q->orderBy('o.name')->paginate(min(max($perPage, 1), 50))->through(fn ($r) => $this->map($r, []));
    }

    public function find(int $id): PlatformOrganizationSummary
    {
        $this->authorize(PlatformCapability::OrganizationsView);
        $r = $this->base()->where('o.id', $id)->first() ?? abort(404);
        $units = DB::table('organization_units as u')->join('organization_unit_types as t', 't.id', '=', 'u.organization_unit_type_id')->where('u.organization_id', $id)->whereNull('u.parent_id')->orderBy('u.name')->limit(20)->get(['u.name', 't.label as type', 'u.status'])->map(fn ($u) => (array) $u)->all();

        return $this->map($r, $units);
    }

    /** @return list<PlatformOrganizationSummary> */
    public function search(string $term, int $limit): array
    {
        $this->authorize(PlatformCapability::OrganizationsView);
        $term = mb_strtolower(trim($term));

        return $this->base()->where(fn (Builder $q) => $q->whereRaw('lower(o.name) like ?', [$term.'%'])->orWhereRaw('lower(o.slug) like ?', [$term.'%'])->orWhereRaw('lower(o.uuid) like ?', [$term.'%']))->limit($limit)->get()->map(fn ($r) => $this->map($r, []))->all();
    }

    private function base(): Builder
    {
        return DB::table('organizations as o')->leftJoin('organization_units as root', 'root.id', '=', 'o.root_unit_id')->select(['o.id', 'o.uuid', 'o.name', 'o.slug', 'o.status', 'root.name as root_unit', 'o.created_at'])
            ->selectSub(fn ($q) => $q->from('organization_units as uc')->selectRaw('count(*)')->whereColumn('uc.organization_id', 'o.id'), 'unit_count')
            ->selectSub(fn ($q) => $q->from('church_organization_assignments as ac')->selectRaw('count(*)')->whereColumn('ac.organization_id', 'o.id')->where('ac.status', 'active'), 'church_count')
            ->selectSub(fn ($q) => $q->from('church_organization_assignments as pc')->selectRaw('count(*)')->whereColumn('pc.organization_id', 'o.id')->where('pc.status', 'pending'), 'pending_count')
            ->selectSub(fn ($q) => $q->from('organization_memberships as mc')->selectRaw('count(*)')->whereColumn('mc.organization_id', 'o.id')->where('mc.status', 'active'), 'membership_count');
    }

    private function map(object $r, array $units): PlatformOrganizationSummary
    {
        return new PlatformOrganizationSummary((int) $r->id, $r->uuid, $r->name, $r->slug, $r->status, $r->root_unit, (int) $r->unit_count, (int) $r->church_count, (int) $r->pending_count, (int) $r->membership_count, $units, $this->date($r->created_at) ?? '');
    }
}
