<?php

namespace App\Platform\Read;

use App\Enums\PlatformCapability;
use App\Platform\Read\Dto\PlatformDeliverySummary;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class PlatformDeliveryQuery extends PlatformReadQuery
{
    public function paginate(string $status = '', string $type = '', string $failure = '', int $perPage = 20): LengthAwarePaginator
    {
        $this->authorize(PlatformCapability::DeliveriesView);
        $query = $this->base();
        if ($status !== '') {
            $query->where('d.status', $status);
        }if ($type !== '') {
            $query->where('d.subject_type', $type);
        }if ($failure !== '') {
            $query->where('d.failure_category', $failure);
        }

        return $query->orderByDesc('d.requested_at')->paginate(min(max($perPage, 1), 50))->through(fn ($row) => $this->map($row, true));
    }

    public function find(int $id): PlatformDeliverySummary
    {
        $this->authorize(PlatformCapability::DeliveriesView);

        return $this->map($this->base()->where('d.id', $id)->first() ?? abort(404), false);
    }

    private function base(): Builder
    {
        return DB::table('invitation_delivery_attempts as d')->leftJoin('church_activations as ca', 'ca.id', '=', 'd.church_activation_id')->leftJoin('church_staff_invitations as si', 'si.id', '=', 'd.church_staff_invitation_id')->leftJoin('churches as c', function ($join) {
            $join->on('c.id', '=', 'ca.church_id')->orOn('c.id', '=', 'si.church_id');
        })->select(['d.id', 'd.uuid', 'd.subject_type', 'c.id as church_id', 'c.name as church_name', 'd.recipient_email', 'd.status', 'd.attempt_count', 'd.failure_category', 'd.provider_message_reference', 'd.provider_account_key', 'd.requested_at', 'd.queued_at', 'd.provider_accepted_at', 'd.failed_at', 'd.bounced_at', 'd.complained_at']);
    }

    private function map(object $r, bool $mask): PlatformDeliverySummary
    {
        return new PlatformDeliverySummary((int) $r->id, $r->uuid, $r->subject_type, $r->church_id ? (int) $r->church_id : null, $r->church_name, $mask ? $this->maskEmail($r->recipient_email) : $r->recipient_email, $r->status, (int) $r->attempt_count, $r->failure_category, $r->provider_message_reference, $r->provider_account_key, $this->date($r->requested_at) ?? '', $this->date($r->queued_at), $this->date($r->provider_accepted_at), $this->date($r->failed_at), $this->date($r->bounced_at), $this->date($r->complained_at));
    }
}
