<?php

namespace Tests\Feature\Marketplace;

use App\Enums\ChurchRole;
use App\Enums\MarketplaceAccessType;
use App\Enums\MarketplaceDownloadOutcome;
use App\Enums\MarketplacePublicationStatus;
use App\Enums\MarketplaceRightsStatus;
use App\Marketplace\AcquireMarketplaceItem;
use App\Marketplace\MarketplaceSourceDelivery;
use App\Marketplace\MarketplaceSourceManager;
use App\Marketplace\ReviewMarketplaceSourceRights;
use App\Models\Church;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceDownload;
use App\Models\MarketplaceItem;
use App\Models\MarketplaceSourceVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\Concerns\VerifiesMarketplaceRights;
use Tests\TestCase;

class MarketplaceRightsGateTest extends TestCase
{
    use RefreshDatabase;
    use VerifiesMarketplaceRights;

    private Church $church;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('marketplace');
        $this->church = Church::factory()->create();
        $this->user = User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($this->user);
    }

    public function test_pending_rejected_and_revoked_rights_cannot_publish_or_become_available(): void
    {
        foreach ([MarketplaceRightsStatus::PENDING, MarketplaceRightsStatus::REJECTED] as $status) {
            [$item, $source] = $this->draftSource();

            if ($status === MarketplaceRightsStatus::REJECTED) {
                app(ReviewMarketplaceSourceRights::class)->reject($source, 'test-platform-operator', 'Evidence was insufficient.');
                $source = $source->fresh();
            }

            try {
                app(MarketplaceSourceManager::class)->makeAvailable($source);
                $this->fail("{$status->value} rights became available.");
            } catch (ValidationException) {
                $this->assertNotSame(MarketplacePublicationStatus::PUBLISHED, $item->fresh()->publication_status);
            }

            $this->expectPublishFailure($item);
        }

        [$item, $source] = $this->publishedSource();
        app(ReviewMarketplaceSourceRights::class)->revoke($source, 'test-platform-operator', 'Redistribution authority was withdrawn.');
        $this->expectPublishFailure($item->fresh());
    }

    public function test_verified_rights_require_complete_evidence_and_valid_technical_state(): void
    {
        [$item, $source] = $this->draftSource();

        try {
            $this->verifyMarketplaceRights($source, ['rightsholder_name' => null]);
            $this->fail('Incomplete rights evidence was verified.');
        } catch (ValidationException) {
            $this->assertSame(MarketplaceRightsStatus::PENDING, $source->fresh()->rights_status);
        }

        $source = $this->verifyMarketplaceRights($source);
        $this->assertSame('platform_operator', $source->rights_verified_by_type);
        $this->assertSame('test-platform-operator', $source->rights_verified_by_reference);
        $this->assertNotNull($source->rights_verified_at);

        DB::table('marketplace_source_versions')->where('id', $source->id)->update(['validation_status' => 'invalid']);

        $this->expectException(ValidationException::class);
        app(MarketplaceSourceManager::class)->makeAvailable($source->fresh());
    }

    public function test_bundled_fonts_require_redistribution_clearance_but_external_dependencies_do_not(): void
    {
        [, $external] = $this->draftSource();
        $external = $this->verifyMarketplaceRights($external, [
            'font_metadata' => [
                'declaration_status' => 'publisher_declared',
                'fonts' => [['family' => 'Commercial Sans', 'bundled' => false]],
                'font_files_bundled' => false,
            ],
        ]);
        app(MarketplaceSourceManager::class)->makeAvailable($external);
        $this->assertTrue(true);

        [, $bundled] = $this->draftSource();
        $this->expectException(ValidationException::class);
        $this->verifyMarketplaceRights($bundled, [
            'font_metadata' => [
                'declaration_status' => 'publisher_declared',
                'fonts' => [['family' => 'Bundled Sans', 'bundled' => true]],
                'font_files_bundled' => true,
                'bundled_font_redistribution_cleared' => false,
            ],
        ]);
    }

    public function test_pending_legacy_publication_cannot_acquire_or_redownload_and_history_is_preserved(): void
    {
        [$item, $source] = $this->publishedSource();
        $acquisition = app(AcquireMarketplaceItem::class)->handle($item);
        app(MarketplaceSourceDelivery::class)->issue($item);
        $issuedIds = MarketplaceDownload::query()->pluck('id')->all();

        DB::table('marketplace_source_versions')->where('id', $source->id)->update([
            'rights_status' => MarketplaceRightsStatus::PENDING->value,
        ]);

        try {
            app(MarketplaceSourceDelivery::class)->issue($item->fresh());
            $this->fail('Pending rights allowed a historical re-download.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('marketplace_acquisitions', ['id' => $acquisition->id]);
            $this->assertDatabaseHas('marketplace_downloads', ['id' => $issuedIds[0], 'outcome' => MarketplaceDownloadOutcome::ISSUED->value]);
            $this->assertDatabaseHas('marketplace_downloads', ['failure_code' => 'marketplace_source_rights_unavailable']);
        }

        $otherChurch = Church::factory()->create();
        $otherUser = User::factory()->forChurch($otherChurch, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($otherUser);
        $this->expectException(ValidationException::class);
        app(AcquireMarketplaceItem::class)->handle($item->fresh());
    }

    public function test_rejected_and_revoked_rights_deny_historical_redownload_without_rewriting_history(): void
    {
        foreach ([MarketplaceRightsStatus::REJECTED, MarketplaceRightsStatus::REVOKED] as $status) {
            [$item, $source] = $this->publishedSource();
            $acquisition = app(AcquireMarketplaceItem::class)->handle($item);
            app(MarketplaceSourceDelivery::class)->issue($item);
            $issued = MarketplaceDownload::query()->latest('id')->firstOrFail();

            if ($status === MarketplaceRightsStatus::REJECTED) {
                app(ReviewMarketplaceSourceRights::class)->reject($source, 'test-platform-operator', 'Evidence rejected after review.');
            } else {
                app(ReviewMarketplaceSourceRights::class)->revoke($source, 'test-platform-operator', 'Rights were withdrawn.');
            }

            try {
                app(MarketplaceSourceDelivery::class)->issue($item->fresh());
                $this->fail("{$status->value} rights allowed a re-download.");
            } catch (ValidationException) {
                $this->assertDatabaseHas('marketplace_acquisitions', ['id' => $acquisition->id]);
                $this->assertDatabaseHas('marketplace_downloads', ['id' => $issued->id, 'outcome' => MarketplaceDownloadOutcome::ISSUED->value]);
                $this->assertSame(MarketplacePublicationStatus::UNPUBLISHED, $item->fresh()->publication_status);
            }
        }
    }

    public function test_direct_rights_and_publication_mutation_cannot_bypass_domain_actions(): void
    {
        [$item, $source] = $this->draftSource();

        try {
            $source->forceFill(['rights_status' => MarketplaceRightsStatus::VERIFIED])->save();
            $this->fail('Direct rights mutation bypassed review.');
        } catch (LogicException) {
            $this->assertSame(MarketplaceRightsStatus::PENDING, $source->fresh()->rights_status);
        }

        try {
            $source->forceFill(['availability_status' => 'available', 'available_at' => now()])->save();
            $this->fail('Direct availability mutation bypassed the source manager.');
        } catch (LogicException) {
            $this->assertNotSame('available', $source->fresh()->availability_status->value);
        }

        $this->expectException(LogicException::class);
        $item->forceFill([
            'publication_status' => MarketplacePublicationStatus::PUBLISHED,
            'published_at' => now(),
        ])->save();
    }

    /** @return array{MarketplaceItem, MarketplaceSourceVersion} */
    private function draftSource(): array
    {
        $category = MarketplaceCategory::firstOrCreate(['slug' => 'services'], ['name' => 'Services']);
        $item = MarketplaceItem::create([
            'marketplace_category_id' => $category->id,
            'title' => 'Rights test '.uniqid(),
            'slug' => 'rights-test-'.uniqid(),
            'short_description' => 'Synthetic rights-gate product.',
            'access_type' => MarketplaceAccessType::FREE,
            'publisher_name' => 'Keryon',
        ]);
        $source = app(MarketplaceSourceManager::class)->register($item, 1, "PK\x03\x04rights-test".uniqid(), 'source.zip');

        return [$item, $source];
    }

    /** @return array{MarketplaceItem, MarketplaceSourceVersion} */
    private function publishedSource(): array
    {
        [$item, $source] = $this->draftSource();
        $source = $this->verifyMarketplaceRights($source);
        app(MarketplaceSourceManager::class)->makeAvailable($source);
        $item->publish();

        return [$item->fresh(), $source->fresh()];
    }

    private function expectPublishFailure(MarketplaceItem $item): void
    {
        try {
            $item->publish();
            $this->fail('An item without verified rights was published.');
        } catch (LogicException) {
            $this->assertNotSame(MarketplacePublicationStatus::PUBLISHED, $item->fresh()->publication_status);
        }
    }
}
