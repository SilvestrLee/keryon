<?php

namespace App\Filament\Central\Pages;

use App\Enums\PlatformCapability;
use App\Filament\Central\Concerns\InteractsWithPlatformWorkspace;
use App\Platform\Read\PlatformOrganizationQuery;
use Filament\Pages\Page;

class OrganizationDetail extends Page
{
    use InteractsWithPlatformWorkspace;

    protected string $view = 'filament.central.pages.detail';

    protected static ?string $slug = 'organizations/{record}';

    protected static bool $shouldRegisterNavigation = false;

    public int $record;

    protected static function requiredCapability(): PlatformCapability
    {
        return PlatformCapability::OrganizationsView;
    }

    public function item()
    {
        return app(PlatformOrganizationQuery::class)->find($this->record);
    }

    public function sections($r): array
    {
        $sections = ['Identity' => ['Name' => $r->name, 'UUID' => $r->uuid, 'Slug' => $r->slug, 'Status' => $r->status, 'Created' => $r->createdAt], 'Structure' => ['Root unit' => $r->rootUnit ?? 'Not set', 'Units' => (string) $r->unitCount, 'Assigned Churches' => (string) $r->churchCount, 'Pending assignments' => (string) $r->pendingAssignmentCount, 'Active members' => (string) $r->membershipCount]];
        if ($r->firstLevelUnits) {
            $sections['Root-level units'] = collect($r->firstLevelUnits)->mapWithKeys(fn ($u) => [$u['name'] => $u['type'].' · '.$u['status']])->all();
        }

        return $sections;
    }
}
