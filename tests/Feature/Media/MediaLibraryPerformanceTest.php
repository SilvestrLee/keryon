<?php

namespace Tests\Feature\Media;

use App\Enums\ChurchRole;
use App\Filament\Pages\MediaLibrary;
use App\Media\ChurchMediaLibraryQuery;
use App\Media\IngestMediaAsset;
use App\Models\Church;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * K-MEDIA-V1-001B §45 — performance evidence at 0/12/50 assets, measured
 * before any thumbnail-pipeline decision. Per §72 stop condition 3, a new
 * rendition pipeline is only warranted if this evidence shows a real
 * problem — it does not.
 */
class MediaLibraryPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Storage::fake('media-private');
        $this->church = Church::factory()->create();
        $this->actingAs(User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create());
    }

    protected function fakePngBytes(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    }

    protected function ingestMany(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $staged = "tenants/{$this->church->id}/media/.staging/".uniqid().'.tmp';
            Storage::disk('media-private')->put($staged, $this->fakePngBytes());
            app(IngestMediaAsset::class)->handle($staged, "asset-{$i}.png");
        }
    }

    protected function queryCountForRender(): int
    {
        DB::enableQueryLog();
        Livewire::test(MediaLibrary::class)->assertSuccessful();
        $count = count(DB::getQueryLog());
        DB::flushQueryLog();
        DB::disableQueryLog();

        return $count;
    }

    public function test_query_count_at_zero_assets(): void
    {
        $count = $this->queryCountForRender();
        $this->assertLessThan(30, $count);
    }

    public function test_query_count_at_twelve_assets(): void
    {
        $this->ingestMany(12);
        $count = $this->queryCountForRender();
        $this->assertLessThan(30, $count);
    }

    public function test_query_count_at_fifty_assets(): void
    {
        $this->ingestMany(50);
        $count = $this->queryCountForRender();
        $this->assertLessThan(30, $count);

        // Pagination proof: 50 assets, default page size 24 -> page 1
        // renders exactly 24 cards, not all 50.
        $rendered = app(ChurchMediaLibraryQuery::class)->paginate();
        $this->assertSame(50, $rendered->total());
        $this->assertCount(24, $rendered->items());
    }
}
