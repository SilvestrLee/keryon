<?php

namespace Tests\Feature\Design;

use App\Design\Actions\CreateDesign;
use App\Design\Actions\RenderDesignOutput;
use App\Design\Actions\RetryDesignOutput;
use App\Design\Rendering\DesignRenderer;
use App\Design\Rendering\DesignRenderingContext;
use App\Design\Rendering\Exceptions\DesignRendererException;
use App\Design\Rendering\RenderedDesignFile;
use App\Enums\ChurchRole;
use App\Enums\DesignOutputFormat;
use App\Enums\DesignOutputStatus;
use App\Enums\DesignPurpose;
use App\Models\CampaignMedia;
use App\Models\Church;
use App\Models\DesignOutput;
use App\Models\MediaAsset;
use App\Models\User;
use App\Campaigns\CampaignManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DesignRenderingFlowTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('rendered');
        config(['design-renderer.disk' => 'rendered']);
        $this->church = Church::create(['name' => 'Grace Community Church', 'slug' => 'grace-rendering']);
        $this->user = User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($this->user);
    }

    public function test_rendered_file_becomes_canonical_media_and_output(): void
    {
        $design = $this->design([DesignOutputFormat::SQUARE]);
        $this->fakeRenderer(fn ($context, $format) => $this->png($format));

        $output = app(RenderDesignOutput::class)->handle($design->outputs->first());

        $this->assertSame(DesignOutputStatus::RENDERED, $output->status);
        $this->assertNotNull($output->media_asset_id);
        $this->assertSame([1080, 1080], [$output->mediaAsset->width, $output->mediaAsset->height]);
        Storage::disk('rendered')->assertExists($output->mediaAsset->path);
        $this->assertStringNotContainsString('Grace', $output->mediaAsset->path);
        $this->assertSame(1, MediaAsset::query()->count());
    }

    public function test_partial_failure_survives_and_failed_output_has_narrow_retry_seam(): void
    {
        $design = $this->design([DesignOutputFormat::SQUARE, DesignOutputFormat::PORTRAIT]);
        $this->fakeRenderer(fn ($context, $format) => $format === DesignOutputFormat::PORTRAIT ? throw new DesignRendererException('renderer_timeout') : $this->png($format));

        foreach ($design->outputs as $output) app(RenderDesignOutput::class)->handle($output);

        $this->assertSame(DesignOutputStatus::RENDERED, $design->outputs()->where('format', 'square')->first()->status);
        $failed = $design->outputs()->where('format', 'portrait')->first();
        $this->assertSame(DesignOutputStatus::FAILED, $failed->status);
        $this->assertNull($failed->media_asset_id);
        $this->assertSame(1, MediaAsset::query()->count());
        $this->expectException(\LogicException::class);
        $design->approve($this->user);
    }

    public function test_failed_output_can_return_to_pending_without_queue_or_ui(): void
    {
        $output = $this->design([DesignOutputFormat::STORY])->outputs->first();
        $output->markFailed('renderer_timeout');

        $retried = app(RetryDesignOutput::class)->handle($output);

        $this->assertSame(DesignOutputStatus::PENDING, $retried->status);
        $this->assertNull($retried->failure_code);
    }

    public function test_campaign_outputs_are_associated_once_on_approval_and_standalone_outputs_are_not(): void
    {
        $campaign = app(CampaignManager::class)->create(['title' => 'Sunday Encounter']);
        $campaignDesign = $this->design([DesignOutputFormat::SQUARE], $campaign->id);
        $standalone = $this->design([DesignOutputFormat::PORTRAIT]);
        $this->fakeRenderer(fn ($context, $format) => $this->png($format));
        app(RenderDesignOutput::class)->handle($campaignDesign->outputs->first());
        app(RenderDesignOutput::class)->handle($standalone->outputs->first());

        $campaignDesign->fresh()->approve($this->user);
        $campaignDesign->fresh()->approve($this->user);

        $this->assertSame(1, CampaignMedia::query()->count());
        $this->assertSame($campaign->id, CampaignMedia::query()->first()->campaign_id);
    }

    private function design(array $formats, ?int $campaignId = null)
    {
        return app(CreateDesign::class)->handle('sunday-modern-reference', 1, DesignPurpose::SERVICE, ['title' => 'Sunday Encounter', 'date' => '2026-08-23', 'time' => '09:30'], $formats, campaignId: $campaignId);
    }

    private function fakeRenderer(callable $callback): void
    {
        $this->app->instance(DesignRenderer::class, new class($callback) implements DesignRenderer {
            public function __construct(private $callback) {}
            public function render(DesignRenderingContext $context, DesignOutputFormat $format): RenderedDesignFile { return ($this->callback)($context, $format); }
        });
    }

    private function png(DesignOutputFormat $format): RenderedDesignFile
    {
        return new RenderedDesignFile("\x89PNG\r\n\x1a\nfixture", 'image/png', 'png', $format->width(), $format->height());
    }
}
