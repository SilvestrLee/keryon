<?php

namespace Tests\Feature\Marketplace;

use App\Enums\ChurchRole;
use App\Enums\MarketplaceAccessType;
use App\Enums\MembershipStatus;
use App\Marketplace\AcquireMarketplaceItem;
use App\Marketplace\MarketplaceCatalogue;
use App\Marketplace\MarketplaceSourceManager;
use App\Models\Church;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceItem;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\VerifiesMarketplaceRights;
use Tests\TestCase;

class MarketplaceAuthorizationTest extends TestCase
{
    use RefreshDatabase;
    use VerifiesMarketplaceRights;

    private Church $church;

    private MarketplaceItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('marketplace');
        $this->church = Church::factory()->create();
        $this->item = $this->publishedItem();
    }

    public function test_guest_and_user_without_membership_cannot_query_private_catalogue(): void
    {
        foreach ([null, User::factory()->create()] as $user) {
            if ($user !== null) {
                $this->actingAs($user);
            }

            try {
                app(MarketplaceCatalogue::class)->published();
                $this->fail('Private Marketplace catalogue was visible without a valid membership.');
            } catch (AuthorizationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_inactive_membership_and_inactive_church_fail_closed(): void
    {
        foreach ([MembershipStatus::INVITED, MembershipStatus::SUSPENDED, MembershipStatus::REMOVED] as $status) {
            $user = User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create();
            $user->memberships()->update(['status' => $status]);
            $this->actingAs($user);
            app(TenantContext::class)->forgetResolved();

            $this->assertDenied();
        }

        $user = User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create();
        $this->church->update(['is_active' => false]);
        $this->actingAs($user);
        app(TenantContext::class)->forgetResolved();
        $this->assertDenied();
    }

    public function test_administrator_care_and_primary_only_are_denied_while_communications_and_composed_roles_are_allowed(): void
    {
        foreach ([
            [[ChurchRole::ADMINISTRATOR], false, false],
            [[ChurchRole::CARE], false, false],
            [[], true, false],
            [[ChurchRole::COMMUNICATIONS], false, true],
            [[ChurchRole::ADMINISTRATOR, ChurchRole::COMMUNICATIONS], false, true],
        ] as [$roles, $primary, $allowed]) {
            $user = User::factory()->forChurch($this->church, $roles, primary: $primary)->create();
            $this->actingAs($user);
            app(TenantContext::class)->forgetResolved();

            if ($allowed) {
                $this->assertCount(1, app(MarketplaceCatalogue::class)->published());
                $this->assertSame($this->church->id, app(AcquireMarketplaceItem::class)->handle($this->item)->church_id);
            } else {
                $this->assertDenied();
            }
        }
    }

    private function assertDenied(): void
    {
        try {
            app(MarketplaceCatalogue::class)->published();
            $this->fail('Marketplace access was not denied.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }
    }

    private function publishedItem(): MarketplaceItem
    {
        $category = MarketplaceCategory::create(['name' => 'Sunday Service', 'slug' => 'sunday-service']);
        $item = MarketplaceItem::create([
            'marketplace_category_id' => $category->id,
            'title' => 'Sunday Service Sample',
            'slug' => 'sunday-service-sample',
            'short_description' => 'Synthetic test listing.',
            'access_type' => MarketplaceAccessType::FREE,
        ]);
        $source = app(MarketplaceSourceManager::class)->register($item, 1, "PK\x03\x04synthetic", 'sample.zip');
        $this->verifyMarketplaceRights($source);
        app(MarketplaceSourceManager::class)->makeAvailable($source);
        $item->publish();

        return $item->fresh();
    }
}
