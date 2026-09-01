<?php

namespace App\Platform\Read;

use App\Enums\PlatformCapability;
use App\Platform\Read\Dto\PlatformSubscriptionSummary;
use App\Support\PlatformContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class PlatformCommercialQuery extends PlatformReadQuery
{
    public function paginate(string $search = '', string $status = '', string $market = '', int $perPage = 20): LengthAwarePaginator
    {
        $this->authorize(PlatformCapability::SubscriptionsView);
        $query = $this->base();
        $term = mb_strtolower(trim($search));
        if ($term !== '') {
            $query->where(fn (Builder $q) => $q->whereRaw('lower(s.uuid) like ?', [$term.'%'])->orWhereRaw('lower(c.name) like ?', [$term.'%'])->orWhereRaw('lower(c.slug) like ?', [$term.'%']));
        }
        if ($status !== '') {
            $query->where('s.status', $status);
        }
        if ($market !== '') {
            $query->where('pm.code', strtoupper($market));
        }

        return $query->orderByDesc('s.created_at')->paginate(min(max($perPage, 1), 50))->through(fn ($row) => $this->map($row, [], []));
    }

    public function find(int $id): PlatformSubscriptionSummary
    {
        $this->authorize(PlatformCapability::SubscriptionsView);
        $row = $this->base()->where('s.id', $id)->first() ?? abort(404);
        $invoices = $payments = [];
        if ($this->canViewBilling()) {
            $invoices = DB::table('invoices')->where('billing_account_id', $row->billing_account_id)->orderByDesc('issued_at')->limit(10)->get(['uuid', 'invoice_number', 'status', 'currency', 'total_minor', 'amount_due_minor', 'issued_at', 'due_at', 'paid_at'])->map(fn ($v) => (array) $v)->all();
            $payments = DB::table('payments')->where('billing_account_id', $row->billing_account_id)->orderByDesc('created_at')->limit(10)->get(['uuid', 'status', 'source', 'currency', 'amount_minor', 'received_at', 'failure_category', 'created_at'])->map(fn ($v) => (array) $v)->all();
        }

        return $this->map($row, $invoices, $payments);
    }

    /** @return list<PlatformSubscriptionSummary> */
    public function search(string $term, int $limit): array
    {
        $this->authorize(PlatformCapability::SubscriptionsView);
        $term = mb_strtolower(trim($term));

        return $this->base()->where(fn (Builder $q) => $q->whereRaw('lower(s.uuid) like ?', [$term.'%'])->orWhereRaw('lower(c.name) like ?', [$term.'%']))->limit($limit)->get()->map(fn ($row) => $this->map($row, [], []))->all();
    }

    private function canViewBilling(): bool
    {
        return app(PlatformContext::class)->hasCapability(PlatformCapability::BillingView);
    }

    private function base(): Builder
    {
        $query = DB::table('subscriptions as s')->join('churches as c', 'c.id', '=', 's.church_id')->join('billing_accounts as ba', 'ba.id', '=', 's.billing_account_id')->join('pricing_markets as pm', 'pm.id', '=', 's.pricing_market_id')->leftJoin('subscription_items as si', 'si.subscription_id', '=', 's.id')->leftJoin('plan_versions as pv', 'pv.id', '=', 'si.plan_version_id')->leftJoin('prices as p', 'p.id', '=', 'si.price_id')->select(['s.id', 's.uuid', 's.church_id', 'c.name as church_name', 's.status', 's.trial_started_at', 's.trial_ends_at', 's.current_period_start', 's.current_period_end', 's.cancel_at_period_end', 'pv.version_code as plan_version', 'pm.code as market', 's.billing_account_id', 's.created_at']);
        if ($this->canViewBilling()) {
            $query->leftJoin('billing_profiles as bp', fn ($join) => $join->on('bp.billing_account_id', '=', 'ba.id')->whereNull('bp.effective_until'))->addSelect(['p.uuid as price', 'p.currency', 'p.amount_minor', 'ba.owner_type as payer_type', 'ba.name as payer_name', 'bp.billing_email']);
        } else {
            $query->selectRaw('NULL as price, NULL as currency, NULL as amount_minor, NULL as payer_type, NULL as payer_name, NULL as billing_email');
        }

        return $query;
    }

    private function map(object $r, array $invoices, array $payments): PlatformSubscriptionSummary
    {
        return new PlatformSubscriptionSummary((int) $r->id, $r->uuid, (int) $r->church_id, $r->church_name, $r->status, $this->date($r->trial_started_at), $this->date($r->trial_ends_at), $this->date($r->current_period_start), $this->date($r->current_period_end), (bool) $r->cancel_at_period_end, $r->plan_version, $r->market, $r->price, $r->currency, $r->amount_minor === null ? null : (int) $r->amount_minor, $r->payer_type, $r->payer_name, $r->billing_email, $invoices, $payments, $this->date($r->created_at) ?? '');
    }
}
