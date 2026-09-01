<?php

namespace App\Platform\Read;

use App\Enums\PlatformCapability;
use App\Platform\Read\Dto\PlatformActivationSummary;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class PlatformActivationQuery extends PlatformReadQuery
{
    public function paginate(string $search = '', string $status = '', string $country = '', string $delivery = '', int $perPage = 20): LengthAwarePaginator
    {
        $this->authorize(PlatformCapability::ActivationsView);
        $query = $this->base();
        $term = mb_strtolower(trim($search));
        if ($term !== '') {
            $query->where(fn (Builder $q) => $q->whereRaw('lower(ca.uuid) like ?', [$term.'%'])->orWhereRaw('lower(c.name) like ?', [$term.'%'])->orWhereRaw('lower(ca.prospective_primary_email) = ?', [$term]));
        }
        if ($status !== '') {
            $query->where('ca.status', $status);
        }
        if ($country !== '') {
            $query->where('c.operating_country_code', strtoupper($country));
        }
        if ($delivery !== '') {
            $query->where('ida.status', $delivery);
        }

        return $query->orderByDesc('ca.created_at')->paginate(min(max($perPage, 1), 50))->through(fn ($row) => $this->map($row, true));
    }

    public function find(int $id): PlatformActivationSummary
    {
        $this->authorize(PlatformCapability::ActivationsView);

        return $this->map($this->base()->where('ca.id', $id)->first() ?? abort(404));
    }

    /** @return list<PlatformActivationSummary> */
    public function search(string $term, int $limit): array
    {
        $this->authorize(PlatformCapability::ActivationsView);
        $term = mb_strtolower(trim($term));

        return $this->base()->where(fn (Builder $q) => $q->whereRaw('lower(ca.uuid) like ?', [$term.'%'])->orWhereRaw('lower(c.name) like ?', [$term.'%'])->orWhereRaw('lower(ca.prospective_primary_email) = ?', [$term]))->limit($limit)->get()->map(fn ($r) => $this->map($r, true))->all();
    }

    private function base(): Builder
    {
        $latestDelivery = DB::table('invitation_delivery_attempts')->selectRaw('church_activation_id, max(id) as latest_id')->whereNotNull('church_activation_id')->groupBy('church_activation_id');

        return DB::table('church_activations as ca')->join('churches as c', 'c.id', '=', 'ca.church_id')
            ->leftJoin('pricing_markets as pm', 'pm.id', '=', 'ca.pricing_market_id')->leftJoin('plan_versions as pv', 'pv.id', '=', 'ca.plan_version_id')
            ->leftJoin('prices as p', 'p.id', '=', 'ca.price_id')->leftJoinSub($latestDelivery, 'ld', 'ld.church_activation_id', '=', 'ca.id')
            ->leftJoin('invitation_delivery_attempts as ida', 'ida.id', '=', 'ld.latest_id')
            ->select(['ca.id', 'ca.uuid', 'ca.church_id', 'c.name as church_name', 'ca.prospective_primary_email', 'ca.status', 'c.operating_country_code as country', 'pm.code as market', 'pv.version_code as plan_version', 'p.uuid as price', 'ca.billing_interval', 'ca.payer_type', 'ca.token_expires_at', 'ca.invitation_sent_at', 'ca.accepted_at', 'ca.revoked_at', 'ida.status as delivery_status', 'ida.attempt_count as delivery_attempts', 'ida.failure_category as delivery_failure', 'ca.provisioning_origin', 'ca.provisioned_by_reference', 'ca.terms_version', 'ca.privacy_version', 'ca.created_at']);
    }

    private function map(object $r, bool $maskEmail = false): PlatformActivationSummary
    {
        return new PlatformActivationSummary((int) $r->id, $r->uuid, (int) $r->church_id, $r->church_name, $maskEmail ? $this->maskEmail($r->prospective_primary_email) : $r->prospective_primary_email, $r->status, $r->country, $r->market, $r->plan_version, $r->price, $r->billing_interval, $r->payer_type, $this->date($r->token_expires_at), $this->date($r->invitation_sent_at), $this->date($r->accepted_at), $this->date($r->revoked_at), $r->delivery_status, (int) ($r->delivery_attempts ?? 0), $r->delivery_failure, $r->provisioning_origin, $r->provisioned_by_reference, $r->terms_version, $r->privacy_version, $this->date($r->created_at) ?? '');
    }
}
