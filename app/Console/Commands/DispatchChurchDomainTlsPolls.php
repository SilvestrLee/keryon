<?php

namespace App\Console\Commands;

use App\Enums\DomainTlsStatus;
use App\Jobs\PollChurchDomainTls;
use App\Models\ChurchDomain;
use Illuminate\Console\Command;

/**
 * K-DOMAIN-001E §20/§21 due-domain selection for TLS provisioning polling.
 * Uses church_domains_tls_status_idx, added by this milestone's migration.
 *
 * K-DOMAIN-001E-R1 §2 — the scheduler itself may fire frequently; the
 * configured tls_poll_interval_minutes cadence is enforced here, in the
 * due-domain query, not by how often the scheduler runs.
 */
class DispatchChurchDomainTlsPolls extends Command
{
    protected $signature = 'domains:dispatch-tls-polls';

    protected $description = 'Dispatch PollChurchDomainTls for domains awaiting TLS provisioning that are due for another poll';

    public function handle(): int
    {
        $batchSize = (int) config('public-website.custom_domains.dispatch_batch_size', 50);
        // §4 — a zero/negative interval would cause continuous polling;
        // clamp to a minimum of 1 minute, matching the existing repository
        // convention (e.g. MaterializeOrganizationCommunicationDistribution).
        $intervalMinutes = max(1, (int) config('public-website.custom_domains.tls_poll_interval_minutes', 5));
        $threshold = now()->subMinutes($intervalMinutes);

        $ids = ChurchDomain::withoutGlobalScope('church_tenant')
            ->where('tls_status', DomainTlsStatus::Provisioning->value)
            ->whereNull('disabled_at')
            ->whereNull('released_at')
            ->where(function ($query) use ($threshold): void {
                $query->whereNull('last_checked_at')->orWhere('last_checked_at', '<=', $threshold);
            })
            ->orderBy('id')
            ->limit($batchSize)
            ->pluck('id');

        foreach ($ids as $id) {
            PollChurchDomainTls::dispatch($id);
        }

        $this->info("Dispatched {$ids->count()} church domain TLS poll(s).");

        return self::SUCCESS;
    }
}
