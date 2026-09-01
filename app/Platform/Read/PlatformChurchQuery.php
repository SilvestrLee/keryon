<?php

namespace App\Platform\Read;

use App\Enums\DomainStatus;
use App\Enums\DomainTlsStatus;
use App\Enums\PlatformCapability;
use App\Models\Church;
use App\Platform\Read\Dto\PlatformChurchSummary;
use App\Website\ChurchPublicUrlResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class PlatformChurchQuery extends PlatformReadQuery
{
    public function paginate(string $search = '', string $state = '', string $country = '', string $activation = '', int $perPage = 20): LengthAwarePaginator
    {
        $this->authorize(PlatformCapability::ChurchesView);
        $query = $this->base();
        if ($search !== '') {
            $term = mb_strtolower(trim($search));
            $query->where(function ($nested) use ($term): void {
                $nested->whereRaw('lower(c.name) like ?', [$term.'%'])
                    ->orWhereRaw('lower(c.slug) like ?', [$term.'%'])
                    ->orWhereRaw('lower(pu.email) = ?', [$term])
                    ->orWhere('c.id', ctype_digit($term) ? (int) $term : -1);
            });
        }
        if ($state !== '') {
            $query->where('c.is_active', $state === 'active');
        }
        if ($country !== '') {
            $query->where('c.operating_country_code', strtoupper($country));
        }
        if ($activation !== '') {
            $query->where('ca.status', $activation);
        }

        return $query->orderBy('c.name')->paginate(min(max($perPage, 1), 50))->through(fn ($row) => $this->map($row, true));
    }

    public function find(int $id): PlatformChurchSummary
    {
        $this->authorize(PlatformCapability::ChurchesView);
        $row = $this->base()->where('c.id', $id)->first() ?? abort(404);
        $church = Church::query()->select(['id', 'slug', 'is_active'])->findOrFail($id);
        $row->public_url = app(ChurchPublicUrlResolver::class)->resolve($church);

        return $this->map($row);
    }

    /** @return list<PlatformChurchSummary> */
    public function search(string $term, int $limit): array
    {
        $this->authorize(PlatformCapability::ChurchesView);
        $term = mb_strtolower(trim($term));

        return $this->base()->where(fn ($q) => $q->whereRaw('lower(c.name) like ?', [$term.'%'])->orWhereRaw('lower(c.slug) like ?', [$term.'%'])->orWhereRaw('lower(pu.email) = ?', [$term]))->orderBy('c.name')->limit($limit)->get()->map(fn ($r) => $this->map($r, true))->all();
    }

    private function base(): Builder
    {
        return DB::table('churches as c')
            ->leftJoin('church_memberships as pm', fn ($join) => $join->on('pm.church_id', '=', 'c.id')->where('pm.is_primary', true)->where('pm.status', 'active'))
            ->leftJoin('users as pu', 'pu.id', '=', 'pm.user_id')
            ->leftJoin('church_organization_assignments as coa', 'coa.id', '=', 'c.current_organization_assignment_id')
            ->leftJoin('organizations as o', 'o.id', '=', 'coa.organization_id')
            ->leftJoin('organization_units as ou', 'ou.id', '=', 'coa.organization_unit_id')
            ->leftJoin('subscriptions as s', 's.id', '=', 'c.current_subscription_id')
            ->leftJoin('subscription_items as si', 'si.subscription_id', '=', 's.id')
            ->leftJoin('plan_versions as pv', 'pv.id', '=', 'si.plan_version_id')
            ->leftJoin('website_settings as ws', 'ws.church_id', '=', 'c.id')
            ->leftJoin('website_publications as wp', 'wp.id', '=', 'ws.current_publication_id')
            ->leftJoin('church_domains as cd', fn ($join) => $join->on('cd.church_id', '=', 'c.id')->where('cd.is_primary', true)->whereNull('cd.released_at'))
            ->leftJoin('church_activations as ca', 'ca.church_id', '=', 'c.id')
            ->select([
                'c.id', 'c.name', 'c.slug', 'c.is_active', 'c.operating_country_code', 'c.timezone', 'c.activated_at', 'c.created_at',
                'pu.name as primary_name', 'pu.email as primary_email', 'pm.status as primary_status',
                'o.name as organization_name', 'ou.name as organization_unit', 'coa.status as assignment_status',
                's.status as subscription_status', 's.trial_ends_at', 'pv.version_code as plan_version',
                'wp.published_at as website_published_at', 'cd.status as domain_status', 'cd.tls_status as domain_tls_status',
                'cd.ownership_verified_at', 'cd.routing_verified_at', 'cd.tls_ready_at', 'cd.disabled_at', 'ca.status as activation_status',
            ]);
    }

    private function map(object $row, bool $maskEmail = false): PlatformChurchSummary
    {
        $eligibleDomain = $row->domain_status === DomainStatus::Active->value
            && $row->domain_tls_status === DomainTlsStatus::Ready->value
            && $row->ownership_verified_at !== null && $row->routing_verified_at !== null
            && $row->tls_ready_at !== null && $row->disabled_at === null;
        $domainState = $row->domain_status === null ? 'Keryon address' : ($eligibleDomain ? 'Connected' : str($row->domain_status)->replace('_', ' ')->title()->toString());

        return new PlatformChurchSummary(
            (int) $row->id, $row->name, $row->slug, (bool) $row->is_active, $row->operating_country_code,
            $row->timezone, $this->date($row->activated_at), $this->date($row->created_at), $row->primary_name,
            $row->primary_email === null ? null : ($maskEmail ? $this->maskEmail($row->primary_email) : $row->primary_email), $row->primary_status, $row->organization_name, $row->organization_unit,
            $row->assignment_status, $row->subscription_status, $this->date($row->trial_ends_at), $row->plan_version,
            $row->website_published_at !== null, $this->date($row->website_published_at), $row->public_url ?? null,
            $domainState, $row->activation_status,
        );
    }
}
