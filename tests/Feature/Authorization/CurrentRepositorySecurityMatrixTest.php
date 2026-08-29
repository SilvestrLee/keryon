<?php

namespace Tests\Feature\Authorization;

use App\Enums\ChurchRole;
use App\Filament\Pages\CareCenterDashboard;
use App\Filament\Widgets\CongregationStatsWidget;
use App\Models\Campaign;
use App\Models\Church;
use App\Models\CongregationMember;
use App\Models\ContentItem;
use App\Models\Design;
use App\Models\FaithFlowRun;
use App\Models\MarketplaceItem;
use App\Models\MediaAsset;
use App\Models\PrayerRequest;
use App\Models\User;
use App\Models\WebsiteSettings;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CurrentRepositorySecurityMatrixTest extends TestCase
{
    use RefreshDatabase;

    /** @param list<string> $roleValues @param array<string, bool> $expected */
    #[DataProvider('roleMatrix')]
    public function test_composable_roles_authorize_the_current_product_surface(array $roleValues, bool $primary, array $expected): void
    {
        $church = Church::factory()->create();
        $roles = array_map(ChurchRole::from(...), $roleValues);
        $user = User::factory()->forChurch($church, $roles, primary: $primary)->create();

        $this->actingAs($user);

        $actual = [
            'congregation' => $user->can('viewAny', CongregationMember::class),
            'care' => $user->can('viewAny', PrayerRequest::class) && CareCenterDashboard::canAccess(),
            'content' => $user->can('viewAny', ContentItem::class),
            'faithflow' => $user->can('viewAny', FaithFlowRun::class),
            'campaigns' => $user->can('viewAny', Campaign::class),
            'website' => $user->can('viewAny', WebsiteSettings::class),
            'media' => $user->can('viewAny', MediaAsset::class),
            'designs' => $user->can('viewAny', Design::class),
            'marketplace' => $user->can('viewAny', MarketplaceItem::class),
        ];

        $this->assertSame($expected, $actual);
        $this->assertSame($expected['congregation'], CongregationStatsWidget::canView());
    }

    public static function roleMatrix(): array
    {
        $admin = self::expectations(congregation: true);
        $communications = self::expectations(
            congregation: true,
            content: true,
            faithflow: true,
            campaigns: true,
            website: true,
            media: true,
            designs: true,
            marketplace: true,
        );
        $care = self::expectations(congregation: true, care: true);

        return [
            'administrator' => [[ChurchRole::ADMINISTRATOR->value], false, $admin],
            'communications' => [[ChurchRole::COMMUNICATIONS->value], false, $communications],
            'care' => [[ChurchRole::CARE->value], false, $care],
            'administrator + communications' => [[ChurchRole::ADMINISTRATOR->value, ChurchRole::COMMUNICATIONS->value], false, self::union($admin, $communications)],
            'administrator + care' => [[ChurchRole::ADMINISTRATOR->value, ChurchRole::CARE->value], false, self::union($admin, $care)],
            'communications + care' => [[ChurchRole::COMMUNICATIONS->value, ChurchRole::CARE->value], false, self::union($communications, $care)],
            'all roles' => [[ChurchRole::ADMINISTRATOR->value, ChurchRole::COMMUNICATIONS->value, ChurchRole::CARE->value], false, self::union($admin, $communications, $care)],
            'primary only' => [[], true, self::expectations()],
        ];
    }

    public function test_missing_tenant_context_denies_every_matrix_surface_and_widget(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        foreach ([
            CongregationMember::class,
            PrayerRequest::class,
            ContentItem::class,
            FaithFlowRun::class,
            Campaign::class,
            WebsiteSettings::class,
            MediaAsset::class,
            Design::class,
            MarketplaceItem::class,
        ] as $model) {
            $this->assertFalse($user->can('viewAny', $model));
        }

        $this->assertFalse(CareCenterDashboard::canAccess());
        $this->assertFalse(CongregationStatsWidget::canView());
        $this->assertNull(app(TenantContext::class)->currentMembership());
    }

    private static function expectations(
        bool $congregation = false,
        bool $care = false,
        bool $content = false,
        bool $faithflow = false,
        bool $campaigns = false,
        bool $website = false,
        bool $media = false,
        bool $designs = false,
        bool $marketplace = false,
    ): array {
        return compact('congregation', 'care', 'content', 'faithflow', 'campaigns', 'website', 'media', 'designs', 'marketplace');
    }

    private static function union(array ...$sets): array
    {
        $result = self::expectations();

        foreach ($sets as $set) {
            foreach ($set as $surface => $allowed) {
                $result[$surface] = $result[$surface] || $allowed;
            }
        }

        return $result;
    }
}
