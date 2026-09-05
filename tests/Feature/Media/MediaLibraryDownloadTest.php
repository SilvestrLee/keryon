<?php

namespace Tests\Feature\Media;

use App\Enums\ChurchRole;
use App\Filament\Pages\MediaLibraryDetail;
use App\Media\IngestMediaAsset;
use App\Models\Church;
use App\Models\MediaAsset;
use App\Models\User;
use App\Support\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * K-MEDIA-V1-001B §61 — download test matrix. Reuses the existing
 * `media.private` route/`PrivateMediaDelivery` service unchanged; no new
 * download endpoint is introduced for the Library.
 */
class MediaLibraryDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Storage::fake('media-private');
    }

    protected function fakePngBytes(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    }

    protected function ingestFor(Church $church): MediaAsset
    {
        $staged = "tenants/{$church->id}/media/.staging/".uniqid().'.tmp';
        Storage::disk('media-private')->put($staged, $this->fakePngBytes());

        return app(IngestMediaAsset::class)->handle($staged, 'hero.png');
    }

    public function test_same_church_authorized_download_succeeds(): void
    {
        $church = Church::factory()->create();
        $this->actingAs(User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create());
        $asset = $this->ingestFor($church);

        $this->get(route('media.private', $asset->uuid))->assertOk();
    }

    public function test_cross_church_download_is_denied(): void
    {
        $churchA = Church::factory()->create();
        $churchB = Church::factory()->create();
        $this->actingAs(User::factory()->forChurch($churchA, [ChurchRole::COMMUNICATIONS])->create());
        $asset = $this->ingestFor($churchA);

        $this->actingAs(User::factory()->forChurch($churchB, [ChurchRole::COMMUNICATIONS])->create());
        app(TenantContext::class)->forgetResolved();

        $this->get(route('media.private', $asset->uuid))->assertNotFound();
    }

    public function test_unauthenticated_download_is_denied(): void
    {
        $church = Church::factory()->create();
        $this->actingAs(User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create());
        $asset = $this->ingestFor($church);

        auth()->logout();
        app(TenantContext::class)->forgetResolved();

        $this->get(route('media.private', $asset->uuid))->assertNotFound();
    }

    public function test_response_retains_private_no_store_behaviour(): void
    {
        $church = Church::factory()->create();
        $this->actingAs(User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create());
        $asset = $this->ingestFor($church);

        $response = $this->get(route('media.private', $asset->uuid))->assertOk();

        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_no_raw_storage_url_is_exposed_by_the_detail_page(): void
    {
        $church = Church::factory()->create();
        $this->actingAs(User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create());
        $asset = $this->ingestFor($church);

        Livewire::test(MediaLibraryDetail::class, ['asset' => $asset->uuid])
            ->assertDontSee($asset->path)
            ->assertDontSee($asset->disk);
    }
}
