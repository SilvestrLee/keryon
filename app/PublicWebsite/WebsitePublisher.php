<?php

namespace App\PublicWebsite;

use App\Enums\Capability;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\WebsitePublication;
use App\Models\WebsiteSettings;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class WebsitePublisher
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly WebsiteSnapshot $snapshots,
    ) {}

    public function publish(): WebsitePublication
    {
        $membership = $this->authorizedMembership();
        $church = Church::query()->findOrFail($membership->church_id);

        return DB::transaction(function () use ($membership, $church): WebsitePublication {
            $settings = WebsiteSettings::query()->lockForUpdate()->firstOrFail();
            $snapshot = $this->snapshots->capture($church, $settings);
            $theme = (string) $settings->getRawOriginal('theme');

            $publication = WebsitePublication::query()->create([
                'theme' => $theme,
                'snapshot' => $snapshot,
                'working_fingerprint' => $this->snapshots->fingerprint($snapshot, $theme),
                'published_by' => $membership->user_id,
                'published_at' => now(),
            ]);

            $settings->update(['current_publication_id' => $publication->id]);

            return $publication;
        });
    }

    public function unpublish(): void
    {
        $this->authorizedMembership();

        DB::transaction(function (): void {
            WebsiteSettings::query()->lockForUpdate()->firstOrFail()->update(['current_publication_id' => null]);
        });
    }

    private function authorizedMembership(): ChurchMembership
    {
        $membership = $this->tenant->currentMembership();

        if ($membership === null || ! $membership->hasCapability(Capability::WebsitePublish)) {
            throw new AuthorizationException('You are not authorized to publish this Website.');
        }

        return $membership;
    }
}
