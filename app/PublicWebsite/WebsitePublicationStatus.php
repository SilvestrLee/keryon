<?php

namespace App\PublicWebsite;

use App\Models\WebsitePublication;
use App\Models\WebsiteSettings;
use App\Support\TenantContext;

class WebsitePublicationStatus
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly WebsiteSnapshot $snapshots,
    ) {}

    /** @return array{state: string, current: WebsitePublication|null, latest: WebsitePublication|null, pending: bool} */
    public function current(): array
    {
        $church = $this->tenant->currentChurch();
        $settings = WebsiteSettings::query()->with('currentPublication.publisher')->first();
        $latest = WebsitePublication::query()->with('publisher')->latest('published_at')->first();
        $current = $settings?->currentPublication;

        if ($church === null || $settings === null || $current === null) {
            return [
                'state' => $latest ? 'offline' : 'never_published',
                'current' => null,
                'latest' => $latest,
                'pending' => true,
            ];
        }

        $snapshot = $this->snapshots->capture($church, $settings);
        $fingerprint = $this->snapshots->fingerprint($snapshot, (string) $settings->getRawOriginal('theme'));

        return [
            'state' => 'published',
            'current' => $current,
            'latest' => $latest,
            'pending' => ! hash_equals($current->working_fingerprint, $fingerprint),
        ];
    }
}
