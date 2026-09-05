<?php

namespace Tests\Feature\Media;

use App\Campaigns\CampaignManager;
use App\Campaigns\CampaignMediaManager;
use App\Enums\ChurchRole;
use App\Filament\Pages\MediaLibrary;
use App\Filament\Pages\MediaLibraryDetail;
use App\Media\DeleteMediaAsset;
use App\Media\IngestMediaAsset;
use App\Models\Campaign;
use App\Models\Church;
use App\Models\CongregationMember;
use App\Models\ContentItem;
use App\Models\MediaAsset;
use App\Models\PrayerRequest;
use App\Models\User;
use App\Models\WebsiteHomeContent;
use App\Models\WebsiteSettings;
use App\PublicWebsite\WebsitePublisher;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * K-MEDIA-V1-001B §62 — delete test matrix, plus §63 (no-publication) and
 * §64 (no-other-domain-mutation). Every delete goes through
 * `DeleteMediaAsset::handle()` unchanged — never `$asset->delete()`
 * directly from the UI action (§26).
 */
class MediaLibraryDeleteTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Storage::fake('media-private');
        Storage::fake('media-public');
        $this->church = Church::factory()->create();
        $this->user = User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($this->user);
        WebsiteSettings::create();
    }

    protected function fakePngBytes(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    }

    protected function ingest(string $filename = 'hero.png'): MediaAsset
    {
        $staged = "tenants/{$this->church->id}/media/.staging/".uniqid().'.tmp';
        Storage::disk('media-private')->put($staged, $this->fakePngBytes());

        return app(IngestMediaAsset::class)->handle($staged, $filename);
    }

    // 50. Unused asset soft deletes.
    public function test_unused_asset_soft_deletes(): void
    {
        $asset = $this->ingest('unused.png');

        Livewire::test(MediaLibraryDetail::class, ['asset' => $asset->uuid])
            ->callAction('deleteMedia')
            ->assertHasNoActionErrors();

        $this->assertSoftDeleted($asset);
        Storage::disk('media-private')->assertExists($asset->path);
    }

    // 51. Active Website reference blocks delete, with the required
    // product-level message (§27), not a raw exception.
    public function test_active_website_reference_blocks_delete_with_a_clean_message(): void
    {
        $asset = $this->ingest('published.png');
        WebsiteHomeContent::create(['hero_image_id' => $asset->id]);
        app(WebsitePublisher::class)->publish();

        Livewire::test(MediaLibraryDetail::class, ['asset' => $asset->uuid])
            ->callAction('deleteMedia')
            ->assertNotified('This image could not be deleted');

        $this->assertNull($asset->fresh()->deleted_at);
    }

    // 52. Campaign/Design historical associations remain resolvable.
    public function test_campaign_association_remains_resolvable_after_soft_delete(): void
    {
        $asset = $this->ingest('campaign-linked.png');
        $campaign = app(CampaignManager::class)->create(['title' => 'Historical Campaign']);
        $association = app(CampaignMediaManager::class)->attach($campaign, $asset, 'Hero artwork');

        app(DeleteMediaAsset::class)->handle($asset);

        $this->assertSoftDeleted($asset);
        $this->assertSame($asset->id, $association->fresh()->mediaAsset->id);
    }

    // 53. Direct bypass of the canonical delete service is absent from
    // the UI — proven by code inspection: the action calls
    // DeleteMediaAsset::handle(), not $asset->delete() directly.
    public function test_delete_action_uses_the_canonical_service_not_a_direct_model_call(): void
    {
        $source = file_get_contents(app_path('Filament/Pages/MediaLibraryDetail.php'));

        $this->assertStringContainsString('DeleteMediaAsset::class)->handle(', $source);
        $this->assertStringNotContainsString('->delete();', $source);
    }

    // 54. Deleted asset no longer appears in the Library.
    public function test_deleted_asset_no_longer_appears_in_the_library(): void
    {
        $asset = $this->ingest('gone.png');
        app(DeleteMediaAsset::class)->handle($asset);

        Livewire::test(MediaLibrary::class)
            ->assertDontSee('gone.png')
            ->assertSee('No media yet');
    }

    // ---------------------------------------------------------------
    // §63 — no-publication test
    // ---------------------------------------------------------------

    public function test_upload_view_and_delete_create_zero_publications_or_other_domain_side_effects(): void
    {
        $asset = $this->ingest('bounded.png');
        Livewire::test(MediaLibraryDetail::class, ['asset' => $asset->uuid])->assertSuccessful();
        app(DeleteMediaAsset::class)->handle($asset);

        $this->assertSame(0, WebsiteSettings::query()->whereNotNull('current_publication_id')->count());
        $this->assertSame(0, Campaign::query()->count());
        $this->assertSame(0, ContentItem::query()->count());
    }

    // ---------------------------------------------------------------
    // §64 — no-other-domain-mutation test
    // ---------------------------------------------------------------

    public function test_library_actions_do_not_mutate_care_or_congregation(): void
    {
        $prayerRequest = PrayerRequest::query()->create([
            'title' => 'Untouched request',
            'request' => 'Please pray.',
            'requester_name' => 'A Member',
            'status' => 'new',
            'visibility' => 'private',
            'submitted_at' => now(),
        ]);
        $memberCountBefore = CongregationMember::query()->count();

        $asset = $this->ingest('isolated.png');
        Livewire::test(MediaLibraryDetail::class, ['asset' => $asset->uuid])
            ->callAction('editAltText', data: ['alt_text' => 'Updated'])
            ->assertHasNoActionErrors();
        app(DeleteMediaAsset::class)->handle($asset->fresh());

        $this->assertSame('new', $prayerRequest->fresh()->status->value);
        $this->assertSame($memberCountBefore, CongregationMember::query()->count());
    }
}
