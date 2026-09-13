<?php

namespace App\Console\Commands;

use App\Enums\DomainStatus;
use App\Jobs\CheckChurchDomainHealth;
use App\Models\ChurchDomain;
use Illuminate\Console\Command;

/**
 * K-DOMAIN-001E §20/§21 due-domain selection for ongoing health checks.
 * Reuses the existing church_domains_status_checked_idx (status,
 * last_checked_at) index — no new index was needed for this query.
 */
class DispatchChurchDomainHealthChecks extends Command
{
    protected $signature = 'domains:dispatch-health-checks';

    protected $description = 'Dispatch CheckChurchDomainHealth for due Active/Degraded custom domains';

    public function handle(): int
    {
        $intervalHours = (int) config('public-website.custom_domains.health_check_interval_hours', 12);
        $batchSize = (int) config('public-website.custom_domains.dispatch_batch_size', 50);
        $threshold = now()->subHours($intervalHours);

        $ids = ChurchDomain::withoutGlobalScope('church_tenant')
            ->whereIn('status', [DomainStatus::Active->value, DomainStatus::Degraded->value])
            ->where(function ($query) use ($threshold): void {
                $query->whereNull('last_checked_at')->orWhere('last_checked_at', '<=', $threshold);
            })
            ->orderBy('id')
            ->limit($batchSize)
            ->pluck('id');

        foreach ($ids as $id) {
            CheckChurchDomainHealth::dispatch($id);
        }

        $this->info("Dispatched {$ids->count()} church domain health check(s).");

        return self::SUCCESS;
    }
}
