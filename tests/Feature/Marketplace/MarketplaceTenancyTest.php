<?php

namespace Tests\Feature\Marketplace;

use App\Enums\ChurchRole;
use App\Enums\MarketplaceAccessType;
use App\Enums\MembershipStatus;
use App\Marketplace\AcquireMarketplaceItem;
use App\Marketplace\MarketplaceCatalogue;
use App\Marketplace\MarketplaceSourceDelivery;
use App\Marketplace\MarketplaceSourceManager;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\MarketplaceAcquisition;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceDownload;
use App\Models\MarketplaceItem;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\Concerns\VerifiesMarketplaceRights;
use Tests\TestCase;

class MarketplaceTenancyTest extends TestCase
{
    use RefreshDatabase;
    use VerifiesMarketplaceRights;

    public function test_same_user_switching_church_sees_global_catalogue_but_only_active_church_acquisition(): void
    {
        Storage::fake('marketplace');
        $churchA = Church::factory()->create();
        $churchB = Church::factory()->create();
        $user = User::factory()->forChurch($churchA, [ChurchRole::COMMUNICATIONS])->create();
        $membershipB = ChurchMembership::create([
            'church_id' => $churchB->id,
            'user_id' => $user->id,
            'status' => MembershipStatus::ACTIVE,
            'joined_at' => now(),
        ]);
        $membershipB->assignRoles([ChurchRole::COMMUNICATIONS]);
        $item = $this->publishedItem();
        $this->actingAs($user);

        $this->activate($churchA);
        $acquisitionA = app(AcquireMarketplaceItem::class)->handle($item);
        $this->assertCount(1, app(MarketplaceCatalogue::class)->published());
        $this->assertSame($acquisitionA->id, MarketplaceAcquisition::query()->sole()->id);

        $this->activate($churchB);
        $this->assertCount(1, app(MarketplaceCatalogue::class)->published());
        $this->assertSame(0, MarketplaceAcquisition::query()->count());

        try {
            app(MarketplaceSourceDelivery::class)->issue($item);
            $this->fail('Church B downloaded through Church A acquisition.');
        } catch (ValidationException) {
            $this->assertSame(0, MarketplaceDownload::query()->count());
        }

        $acquisitionB = app(AcquireMarketplaceItem::class)->handle($item);
        $download = app(MarketplaceSourceDelivery::class)->issue($item);

        $this->assertNotSame($acquisitionA->id, $acquisitionB->id);
        $this->assertSame($churchB->id, MarketplaceDownload::query()->findOrFail($download->downloadEventId)->church_id);
    }

    public function test_caller_cannot_forge_acquisition_or_download_church_and_actor(): void
    {
        Storage::fake('marketplace');
        $church = Church::factory()->create();
        $foreignChurch = Church::factory()->create();
        $user = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $item = $this->publishedItem();
        $this->actingAs($user);
        $this->activate($church);

        $source = $item->currentSourceVersion();
        $forged = new MarketplaceAcquisition;
        $forged->forceFill([
            'church_id' => $foreignChurch->id,
            'marketplace_item_id' => $item->id,
            'marketplace_source_version_id' => $source?->id,
            'access_type' => MarketplaceAccessType::FREE,
            'acquisition_basis' => 'free_included',
            'acquired_by' => $user->id,
            'acquired_at' => now(),
        ]);

        $this->expectException(LogicException::class);
        $forged->save();
    }

    private function activate(Church $church): void
    {
        session(['active_church_id' => $church->id]);
        app(TenantContext::class)->forgetResolved();
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
