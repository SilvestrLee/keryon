<?php

namespace Tests\Feature\Design;

use App\Campaigns\CampaignCommunicationManager;
use App\Campaigns\CampaignManager;
use App\Design\Actions\CreateDesign;
use App\Design\Actions\RenderDesignOutput;
use App\Design\Rendering\DesignRenderer;
use App\Design\Rendering\DesignRenderingContext;
use App\Design\Rendering\Exceptions\DesignRendererException;
use App\Design\Rendering\RenderedDesignFile;
use App\Enums\ChurchRole;
use App\Enums\CommunicationChannel;
use App\Enums\DesignOutputFormat;
use App\Enums\DesignOutputStatus;
use App\Enums\DesignPurpose;
use App\Filament\Pages\CampaignWorkspace;
use App\Filament\Pages\DesignStudio;
use App\Models\Church;
use App\Models\ChurchBrandProfile;
use App\Models\ChurchMembership;
use App\Models\Design;
use App\Models\MediaAsset;
use App\Models\User;
use App\Support\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DesignStudioPageTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Storage::fake('rendered');
        config(['design-renderer.disk' => 'rendered']);
        $this->church = Church::create(['name' => 'Grace Community Church', 'slug' => 'design-studio-grace']);
        $this->user = User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($this->user);
    }

    public function test_authorized_user_sees_product_landing_and_intentional_empty_state(): void
    {
        Livewire::test(DesignStudio::class)
            ->assertOk()
            ->assertSee('Church graphics,')
            ->assertSee('What are you creating?')
            ->assertSee('Sunday service')
            ->assertSee('Create your first church graphic')
            ->assertSee('Your Brand')
            ->assertDontSee('Execute Renderer');
    }

    public function test_authorization_and_inactive_membership_hide_design_studio(): void
    {
        $care = User::factory()->forChurch($this->church, [ChurchRole::CARE])->create();
        $this->actingAs($care);
        app(TenantContext::class)->forgetResolved();
        $this->assertFalse(DesignStudio::canAccess());

        $inactive = User::factory()->create();
        $membership = ChurchMembership::factory()->for($inactive)->for($this->church)->suspended()->create();
        $membership->assignRoles([ChurchRole::COMMUNICATIONS]);
        $this->actingAs($inactive);
        app(TenantContext::class)->forgetResolved();
        $this->assertFalse(DesignStudio::canAccess());
    }

    public function test_creation_workspace_uses_registry_slots_brand_formats_and_same_church_media(): void
    {
        $asset = $this->asset('service-background.jpg', 1800, 1800);
        ChurchBrandProfile::create([
            'primary_color' => '#123D34',
            'accent_color' => '#F0BD45',
            'heading_font' => 'playfair_display',
            'body_font' => 'inter',
        ]);

        Livewire::test(DesignStudio::class)
            ->call('startCreating', 'service')
            ->assertSee('Sunday Modern Reference')
            ->assertSee('Shape the message')
            ->assertSee('72 characters')
            ->assertSee('Square')
            ->assertSee('Portrait')
            ->assertSee('Story')
            ->assertSee('Church branding applied automatically')
            ->assertSee($asset->original_filename);
    }

    public function test_user_creates_renders_reviews_and_approves_a_standalone_design(): void
    {
        $this->fakeRenderer(fn (DesignRenderingContext $context, DesignOutputFormat $format) => $this->png($format));

        Livewire::test(DesignStudio::class)
            ->set('create', true)
            ->set('purpose', 'service')
            ->set('inputs.title', 'Sunday Encounter')
            ->set('inputs.date', '2026-08-23')
            ->set('inputs.time', '09:30')
            ->set('formats', ['square', 'portrait', 'story'])
            ->call('createDesign')
            ->assertHasNoErrors();

        $design = Design::query()->with('outputs.mediaAsset')->sole();
        $this->assertNull($design->campaign_id);
        $this->assertCount(3, $design->outputs);
        $this->assertTrue($design->outputs->every->isRendered());

        Livewire::test(DesignStudio::class, ['design' => $design->id])
            ->assertSee('Ready for review')
            ->assertSee('3 of 3 formats ready')
            ->assertSee('Approve design')
            ->call('approveDesign')
            ->assertSee('Design approved');

        $this->assertSame('approved', $design->fresh()->state->value);
    }

    public function test_partial_failure_is_visible_and_only_failed_output_is_retried(): void
    {
        $attempts = 0;
        $this->fakeRenderer(function (DesignRenderingContext $context, DesignOutputFormat $format) use (&$attempts) {
            if ($format === DesignOutputFormat::STORY && $attempts++ === 0) {
                throw new DesignRendererException('renderer_timeout');
            }

            return $this->png($format);
        });

        $design = app(CreateDesign::class)->handle(
            'sunday-modern-reference', 1, DesignPurpose::SERVICE,
            ['title' => 'Partial Sunday', 'date' => '2026-08-23', 'time' => '09:30'],
            [DesignOutputFormat::SQUARE, DesignOutputFormat::STORY],
        );

        foreach ($design->outputs as $output) {
            app(RenderDesignOutput::class)->handle($output);
        }

        $story = $design->outputs()->where('format', 'story')->firstOrFail();
        $squareAssetId = $design->outputs()->where('format', 'square')->firstOrFail()->media_asset_id;

        Livewire::test(DesignStudio::class, ['design' => $design->id])
            ->assertSee('Needs attention')
            ->assertSee('Story couldn’t be created')
            ->assertSee('Your other formats are safe')
            ->assertSee('Retry Story')
            ->assertSeeHtml('disabled')
            ->call('retryOutput', $story->id)
            ->assertSee('Ready for review');

        $this->assertSame($squareAssetId, $design->outputs()->where('format', 'square')->firstOrFail()->media_asset_id);
        $this->assertSame(DesignOutputStatus::RENDERED, $story->fresh()->status);
    }

    public function test_campaign_context_is_displayed_preserved_and_linked_from_workspace(): void
    {
        $campaign = app(CampaignManager::class)->create(['title' => 'Easter 2027']);
        $communication = app(CampaignCommunicationManager::class)->add($campaign, [
            'title' => 'Good Friday Reminder',
            'channel' => CommunicationChannel::INSTAGRAM,
        ]);
        $this->fakeRenderer(fn (DesignRenderingContext $context, DesignOutputFormat $format) => $this->png($format));

        Livewire::test(CampaignWorkspace::class, ['campaign' => $campaign->id])
            ->assertSee('Create design');

        Livewire::withQueryParams(['campaign_communication' => $communication->id])
            ->test(DesignStudio::class)
            ->assertSee('Creating for Easter 2027')
            ->assertSee('Good Friday Reminder')
            ->set('inputs.date', '2027-03-26')
            ->set('inputs.time', '18:00')
            ->set('formats', ['square'])
            ->call('createDesign');

        $design = Design::query()->sole();
        $this->assertSame($campaign->id, $design->campaign_id);
        $this->assertSame($communication->id, $design->campaign_communication_id);
    }

    public function test_cross_church_design_media_and_campaign_context_are_invisible_or_rejected(): void
    {
        $foreignChurch = Church::create(['name' => 'Foreign Church', 'slug' => 'foreign-design-studio']);
        $foreignUser = User::factory()->forChurch($foreignChurch, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($foreignUser);
        app(TenantContext::class)->forgetResolved();
        $foreignAsset = $this->asset('foreign.jpg', 1800, 1800);
        $foreignCampaign = app(CampaignManager::class)->create(['title' => 'Foreign Campaign']);
        $foreignCommunication = app(CampaignCommunicationManager::class)->add($foreignCampaign, ['title' => 'Foreign Reminder', 'channel' => CommunicationChannel::GENERAL]);
        $foreignDesign = app(CreateDesign::class)->handle('sunday-modern-reference', 1, DesignPurpose::SERVICE, ['title' => 'Foreign', 'date' => '2026-08-23', 'time' => '09:30'], [DesignOutputFormat::SQUARE]);

        $this->actingAs($this->user);
        app(TenantContext::class)->forgetResolved();

        Livewire::test(DesignStudio::class, ['create' => true])
            ->assertDontSee($foreignAsset->original_filename)
            ->set('inputs.title', 'Injected')
            ->set('inputs.date', '2026-08-23')
            ->set('inputs.time', '09:30')
            ->set('formats', ['square'])
            ->set('mediaBySlot.background', $foreignAsset->id)
            ->call('createDesign')
            ->assertHasErrors(['media.background']);

        try {
            Livewire::test(DesignStudio::class, ['design' => $foreignDesign->id]);
            $this->fail('Foreign Design was visible.');
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }

        $this->expectException(ModelNotFoundException::class);
        Livewire::withQueryParams(['campaign_communication' => $foreignCommunication->id])->test(DesignStudio::class);
    }

    private function asset(string $filename, int $width, int $height): MediaAsset
    {
        return MediaAsset::create([
            'disk' => 'public',
            'path' => "tenants/{$this->church->id}/media/test/{$filename}",
            'original_filename' => $filename,
            'mime_type' => 'image/jpeg',
            'size' => 1000,
            'width' => $width,
            'height' => $height,
            'alt_text' => 'Church media',
        ]);
    }

    private function fakeRenderer(callable $callback): void
    {
        $this->app->instance(DesignRenderer::class, new class($callback) implements DesignRenderer
        {
            public function __construct(private $callback) {}

            public function render(DesignRenderingContext $context, DesignOutputFormat $format): RenderedDesignFile
            {
                return ($this->callback)($context, $format);
            }
        });
    }

    private function png(DesignOutputFormat $format): RenderedDesignFile
    {
        return new RenderedDesignFile("\x89PNG\r\n\x1a\nfixture", 'image/png', 'png', $format->width(), $format->height());
    }
}
