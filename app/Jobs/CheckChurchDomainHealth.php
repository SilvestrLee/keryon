<?php

namespace App\Jobs;

use App\Domain\ChurchDomainHealthCycle;
use App\Enums\DomainStatus;
use App\Models\ChurchDomain;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * The ongoing health-check call site for an Active or Degraded custom
 * domain — K-DOMAIN-001E §18. Infrastructure health only: ownership,
 * routing, TLS/provider state. No publication/content checks (§19).
 *
 * Mirrors VerifyChurchDomain's existing background-domain pattern: a
 * domain id only, `withoutGlobalScope('church_tenant')` to reload it, no
 * fabricated tenant/membership context (§18).
 */
class CheckChurchDomainHealth implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public int $timeout = 30;

    // Shorter than the health interval — this only prevents two concurrent
    // cycles for the same domain, not the scheduling cadence itself (§22).
    public int $uniqueFor = 600;

    public function __construct(public readonly int $domainId, public readonly string $correlationId = '') {}

    public function uniqueId(): string
    {
        return (string) $this->domainId;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [new RateLimited('domain-provider')];
    }

    public function handle(ChurchDomainHealthCycle $cycle): void
    {
        $domain = ChurchDomain::withoutGlobalScope('church_tenant')->find($this->domainId);

        if ($domain === null || ! in_array($domain->status, [DomainStatus::Active, DomainStatus::Degraded], true)) {
            return; // No longer an ongoing-health-check candidate — safe no-op.
        }

        $correlation = $this->correlationId !== '' ? $this->correlationId : (string) Str::uuid();

        $cycle->run($domain, $correlation);
    }
}
