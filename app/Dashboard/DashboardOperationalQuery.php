<?php

namespace App\Dashboard;

use App\Enums\CampaignStatus;
use App\Enums\ChurchStaffInvitationStatus;
use App\Enums\ContentStatus;
use App\Models\Campaign;
use App\Models\ChurchStaffInvitation;
use App\Models\CongregationMember;
use App\Models\ContentItem;

final class DashboardOperationalQuery
{
    /** @return array{review: int, rejected: int} */
    public function content(): array
    {
        $counts = ContentItem::query()
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as review_count', [ContentStatus::REVIEW->value])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as rejected_count', [ContentStatus::REJECTED->value])
            ->first();

        return ['review' => (int) $counts?->review_count, 'rejected' => (int) $counts?->rejected_count];
    }

    public function pendingStaffInvitations(): int
    {
        return ChurchStaffInvitation::query()->where('status', ChurchStaffInvitationStatus::PENDING)->count();
    }

    /** @return array{active: int, approaching: int} */
    public function campaigns(): array
    {
        $counts = Campaign::query()
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as active_count', [CampaignStatus::ACTIVE->value])
            ->selectRaw(
                'sum(case when status = ? and starts_on between ? and ? then 1 else 0 end) as approaching_count',
                [CampaignStatus::PLANNED->value, today()->toDateString(), today()->addDays(30)->toDateString()],
            )
            ->first();

        return ['active' => (int) $counts?->active_count, 'approaching' => (int) $counts?->approaching_count];
    }

    /** @return array{total: int, visitors: int, inactive: int} */
    public function congregation(): array
    {
        $counts = CongregationMember::query()
            ->selectRaw('count(*) as total_count')
            ->selectRaw("sum(case when status = 'visitor' then 1 else 0 end) as visitor_count")
            ->selectRaw("sum(case when status = 'inactive' then 1 else 0 end) as inactive_count")
            ->first();

        return [
            'total' => (int) $counts?->total_count,
            'visitors' => (int) $counts?->visitor_count,
            'inactive' => (int) $counts?->inactive_count,
        ];
    }
}
