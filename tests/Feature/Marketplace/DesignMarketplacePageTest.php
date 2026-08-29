<?php

namespace Tests\Feature\Marketplace;

use App\Enums\ChurchRole;
use App\Enums\MarketplaceAccessType;
use App\Enums\MarketplacePreviewType;
use App\Filament\Pages\DesignMarketplace;
use App\Filament\Pages\DesignStudio;
use App\Marketplace\MarketplaceSourceManager;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\MarketplaceAcquisition;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceItem;
use App\Models\MarketplacePreview;
use App\Models\User;
use App\Support\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\VerifiesMarketplaceRights;
use Tests\TestCase;

class DesignMarketplacePageTest extends TestCase
{
    use RefreshDatabase;
    use VerifiesMarketplaceRights;

    private Church $church;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Storage::fake('marketplace');
        $this->church = Church::factory()->create();
        $this->user = User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($this->user);
    }

    public function test_design_landing_and_navigation_make_private_marketplace_discoverable(): void
    {
        Livewire::test(DesignStudio::class)
            ->assertSee('Choose your design path')
            ->assertSee('Design Marketplace')
            ->assertSee('Browse Marketplace')
            ->assertSee('AI Design')
            ->assertSee('Not available yet');

        $this->assertSame('Design', DesignStudio::getNavigationLabel());
        $this->assertTrue(DesignMarketplace::canAccess());
    }

    public function test_catalogue_and_detail_show_real_contract_without_private_key_leak(): void
    {
        [$item, $preview] = $this->publishedItem();

        Livewire::withQueryParams(['product' => null])->test(DesignMarketplace::class)
            ->assertSee('Professional designs for church communication')
            ->assertSee('Sunday Service')
            ->assertSee('Free')
            ->assertSee('View design')
            ->assertSee('Marketplace library')
            ->assertDontSee($preview->getRawOriginal('storage_key'));

        Livewire::withQueryParams(['product' => $item->slug])
            ->test(DesignMarketplace::class)
            ->assertSee('Editable PSD')
            ->assertSee('Free for Keryon churches')
            ->assertSee('Get this design')
            ->assertDontSee('Font requirements are being confirmed')
            ->assertDontSee($item->currentSourceVersion()?->getRawOriginal('storage_key'));
    }

    public function test_free_acquisition_is_idempotent_changes_ui_and_downloads_controlled_filename(): void
    {
        [$item] = $this->publishedItem();
        $component = Livewire::withQueryParams(['product' => $item->slug])->test(DesignMarketplace::class);

        $component->call('acquire', $item->slug)
            ->assertSee('In your library')
            ->assertSee('Download design')
            ->call('acquire', $item->slug);

        $this->assertDatabaseCount('marketplace_acquisitions', 1);

        $component->call('download', $item->slug)
            ->assertFileDownloaded('Keryon-Sunday-Service.zip');
    }

    public function test_library_is_tenant_scoped_and_switching_church_changes_acquired_state(): void
    {
        [$item] = $this->publishedItem();
        Livewire::withQueryParams(['product' => $item->slug])->test(DesignMarketplace::class)->call('acquire', $item->slug);

        $secondChurch = Church::factory()->create();
        $membership = ChurchMembership::factory()->for($this->user)->for($secondChurch)->create();
        $membership->assignRoles([ChurchRole::COMMUNICATIONS]);
        session(['active_church_id' => $secondChurch->id]);
        app(TenantContext::class)->forgetResolved();

        Livewire::withQueryParams(['product' => null])->test(DesignMarketplace::class)
            ->assertSee('Your design library is ready')
            ->assertSee('Sunday Service');
        Livewire::withQueryParams(['product' => $item->slug])
            ->test(DesignMarketplace::class)
            ->assertSee('Get this design')
            ->assertDontSee('Ready to download');

        $this->assertSame(0, MarketplaceAcquisition::query()->count());
    }

    public function test_guest_care_only_and_inactive_membership_fail_closed(): void
    {
        auth()->logout();
        app(TenantContext::class)->forgetResolved();
        $this->get('/admin/design-marketplace')->assertRedirect('/admin/login');

        $care = User::factory()->forChurch($this->church, [ChurchRole::CARE])->create();
        $this->actingAs($care);
        app(TenantContext::class)->forgetResolved();
        $this->assertFalse(DesignMarketplace::canAccess());

        $inactive = User::factory()->create();
        $membership = ChurchMembership::factory()->for($inactive)->for($this->church)->suspended()->create();
        $membership->assignRoles([ChurchRole::COMMUNICATIONS]);
        $this->actingAs($inactive);
        app(TenantContext::class)->forgetResolved();
        $this->assertFalse(DesignMarketplace::canAccess());
    }

    public function test_withdrawn_source_remains_in_library_with_safe_unavailable_copy(): void
    {
        [$item] = $this->publishedItem();
        Livewire::withQueryParams(['product' => $item->slug])->test(DesignMarketplace::class)->call('acquire', $item->slug);
        app(MarketplaceSourceManager::class)->withdraw($item->currentSourceVersion());

        Livewire::withQueryParams(['product' => null])->test(DesignMarketplace::class)
            ->assertSee('Sunday Service')
            ->assertSee('Source temporarily unavailable')
            ->assertDontSee('Renderer exception');
    }

    /** @return array{MarketplaceItem, MarketplacePreview} */
    private function publishedItem(): array
    {
        $category = MarketplaceCategory::create(['name' => 'Services', 'slug' => 'services']);
        $item = MarketplaceItem::create([
            'marketplace_category_id' => $category->id,
            'title' => 'Sunday Service',
            'slug' => 'sunday-service',
            'short_description' => 'A professional Sunday service source design.',
            'description' => 'Download and edit this church service PSD externally.',
            'access_type' => MarketplaceAccessType::FREE,
            'publisher_name' => 'Keryon',
        ]);
        $source = app(MarketplaceSourceManager::class)->register($item, 1, "PK\x03\x04private-source", 'Keryon-Sunday-Service.zip', [
            'compatibility_metadata' => ['source_psd' => ['width' => 1260, 'height' => 1260]],
            'font_metadata' => ['declaration_status' => 'pending_publisher_declaration', 'fonts' => [], 'font_files_bundled' => false],
        ]);
        $this->verifyMarketplaceRights($source);
        app(MarketplaceSourceManager::class)->makeAvailable($source);

        $previewBytes = $this->png();
        $key = 'marketplace/previews/opaque/sunday.png';
        Storage::disk('marketplace')->put($key, $previewBytes);
        $preview = new MarketplacePreview(['type' => MarketplacePreviewType::PREVIEW, 'sort_order' => 0, 'alt_text' => 'Sunday Service Marketplace design preview']);
        $preview->forceFill([
            'marketplace_item_id' => $item->id,
            'marketplace_source_version_id' => $source->id,
            'disk' => 'marketplace',
            'storage_key' => $key,
            'mime_type' => 'image/png',
            'size' => strlen($previewBytes),
            'width' => 640,
            'height' => 640,
            'sha256' => hash('sha256', $previewBytes),
        ])->save();
        $item->publish();

        return [$item->fresh(['category', 'previews']), $preview];
    }

    private function png(): string
    {
        $image = imagecreatetruecolor(640, 640);
        imagefill($image, 0, 0, imagecolorallocate($image, 20, 62, 43));
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return is_string($bytes) ? $bytes : '';
    }
}
