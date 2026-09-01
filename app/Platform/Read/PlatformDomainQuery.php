<?php

namespace App\Platform\Read;

use App\Enums\PlatformCapability;
use App\Platform\Read\Dto\PlatformDomainSummary;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class PlatformDomainQuery extends PlatformReadQuery
{
    public function paginate(string $search = '', string $status = '', string $tls = '', string $primary = '', int $perPage = 20): LengthAwarePaginator
    {
        $this->authorize(PlatformCapability::DomainsView);
        $query = $this->base();
        $term = mb_strtolower(trim($search));
        if ($term !== '') {
            $query->where(fn (Builder $q) => $q->whereRaw('lower(d.normalized_hostname) like ?', [$term.'%'])->orWhereRaw('lower(c.name) like ?', [$term.'%']));
        }
        if ($status !== '') {
            $query->where('d.status', $status);
        } if ($tls !== '') {
            $query->where('d.tls_status', $tls);
        } if ($primary !== '') {
            $query->where('d.is_primary', $primary === 'primary');
        }

        return $query->orderByDesc('d.created_at')->paginate(min(max($perPage, 1), 50))->through(fn ($row) => $this->map($row, []));
    }

    public function find(int $id): PlatformDomainSummary
    {
        $this->authorize(PlatformCapability::DomainsView);
        $row = $this->base()->where('d.id', $id)->first() ?? abort(404);
        $events = DB::table('church_domain_events')->where('church_domain_id', $id)->orderByDesc('occurred_at')->limit(30)->get(['event_type as event', 'failure_code as failure', 'occurred_at'])->map(fn ($v) => (array) $v)->all();

        return $this->map($row, $events);
    }

    /** @return list<PlatformDomainSummary> */
    public function search(string $term, int $limit): array
    {
        $this->authorize(PlatformCapability::DomainsView);
        $term = mb_strtolower(trim($term));

        return $this->base()->where(fn (Builder $q) => $q->whereRaw('lower(d.normalized_hostname) like ?', [$term.'%'])->orWhereRaw('lower(c.name) like ?', [$term.'%']))->limit($limit)->get()->map(fn ($r) => $this->map($r, []))->all();
    }

    private function base(): Builder
    {
        return DB::table('church_domains as d')->join('churches as c', 'c.id', '=', 'd.church_id')->select(['d.id', 'd.uuid', 'd.church_id', 'c.name as church_name', 'd.normalized_hostname', 'd.display_hostname', 'd.is_primary', 'd.status', 'd.ownership_verified_at', 'd.routing_verified_at', 'd.tls_status', 'd.failure_code', 'd.consecutive_failures', 'd.last_checked_at', 'd.released_at', 'd.created_at']);
    }

    private function map(object $r, array $events): PlatformDomainSummary
    {
        return new PlatformDomainSummary((int) $r->id, $r->uuid, (int) $r->church_id, $r->church_name, $r->normalized_hostname, $r->display_hostname, (bool) $r->is_primary, $r->status, $r->ownership_verified_at !== null, $r->routing_verified_at !== null, $r->tls_status, $r->failure_code, (int) $r->consecutive_failures, $this->date($r->last_checked_at), $this->date($r->released_at), $events, $this->date($r->created_at) ?? '');
    }
}
