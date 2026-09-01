<?php

namespace App\Filament\Central\Pages;

use App\Enums\PlatformCapability;
use App\Filament\Central\Concerns\InteractsWithPlatformWorkspace;
use App\Platform\Read\PlatformChurchQuery;
use Filament\Pages\Page;

class ChurchDetail extends Page
{
    use InteractsWithPlatformWorkspace;

    protected string $view = 'filament.central.pages.detail';

    protected static ?string $slug = 'churches/{record}';

    protected static bool $shouldRegisterNavigation = false;

    public int $record;

    protected static function requiredCapability(): PlatformCapability
    {
        return PlatformCapability::ChurchesView;
    }

    public function item()
    {
        return app(PlatformChurchQuery::class)->find($this->record);
    }

    public function sections($r): array
    {
        return ['Identity' => ['Name' => $r->name, 'Stable ID' => (string) $r->id, 'Slug' => $r->slug, 'State' => $r->active ? 'Active' : 'Inactive', 'Country' => $r->country ?? 'Not set', 'Timezone' => $r->timezone, 'Activated' => $r->activatedAt ?? 'Not activated'], 'Primary administrator' => ['Name' => $r->primaryName ?? 'Not assigned', 'Email' => $r->primaryEmail ?? 'Not assigned', 'Membership' => $r->primaryStatus ?? 'None'], 'Organization' => ['Organization' => $r->organizationName ?? 'Independent', 'Unit' => $r->organizationUnit ?? 'None', 'Assignment' => $r->assignmentStatus ?? 'None'], 'Commercial' => ['Subscription' => $r->subscriptionStatus ?? 'None', 'Trial ends' => $r->trialEndsAt ?? 'None', 'Plan version' => $r->planVersion ?? 'None'], 'Website' => ['Publication' => $r->websitePublished ? 'Published' : 'Private', 'Published at' => $r->websitePublishedAt ?? 'Never', 'Public URL' => $r->publicUrl ?? 'Unavailable', 'Domain' => $r->domainState], 'Activation' => ['Status' => $r->activationStatus ?? 'None']];
    }
}
