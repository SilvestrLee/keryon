<?php

namespace App\Website;

use App\Enums\DomainStatus;
use App\Enums\DomainTlsStatus;
use App\Models\Church;
use App\Models\ChurchDomain;
use App\PublicWebsite\PublicWebsiteUrl;

final readonly class WebsiteDomainSummaryBuilder
{
    public function __construct(
        private ChurchPublicUrlResolver $officialUrls,
        private PublicWebsiteUrl $firstPartyUrls,
    ) {}

    public function for(Church $church): WebsiteDomainSummary
    {
        $domains = ChurchDomain::query()
            ->whereNull('released_at')
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->get();
        $primary = $domains->firstWhere('is_primary', true);
        $display = $primary ?? $domains->first();
        $keryonUrl = $this->firstPartyUrls->page($church);
        $officialUrl = $this->officialUrls->resolve($church);

        if ($display === null) {
            return new WebsiteDomainSummary('keryon', 'Using your Keryon address', 'You can add a custom domain when you are ready.', null, $keryonUrl, $officialUrl, false);
        }

        if ($primary?->isEligible()) {
            return new WebsiteDomainSummary('connected', $primary->display_hostname, 'Connected', $primary->display_hostname, $keryonUrl, $officialUrl, true);
        }

        $degraded = $display->status === DomainStatus::Degraded
            || $display->tls_status === DomainTlsStatus::Failed
            || ($display->status === DomainStatus::Active && ! $display->isEligible());

        if ($degraded) {
            return new WebsiteDomainSummary('degraded', 'Custom domain needs attention', 'Your Keryon address remains available.', $display->display_hostname, $keryonUrl, $officialUrl, false);
        }

        return new WebsiteDomainSummary('pending', $display->display_hostname, 'Waiting for verification', $display->display_hostname, $keryonUrl, $officialUrl, false);
    }
}
