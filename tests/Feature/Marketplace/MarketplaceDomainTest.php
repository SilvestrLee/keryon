<?php

namespace Tests\Feature\Marketplace;

use App\Enums\ChurchRole;
use App\Enums\MarketplaceAccessType;
use App\Enums\MarketplaceDownloadOutcome;
use App\Enums\MarketplacePreviewType;
use App\Enums\MarketplacePublicationStatus;
use App\Marketplace\AcquireMarketplaceItem;
use App\Marketplace\MarketplaceCatalogue;
use App\Marketplace\MarketplacePreviewDelivery;
use App\Marketplace\MarketplaceSourceDelivery;
use App\Marketplace\MarketplaceSourceManager;
use App\Models\Church;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceDownload;
use App\Models\MarketplaceItem;
use App\Models\MarketplacePreview;
use App\Models\MarketplaceSourceVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;
use Tests\Concerns\VerifiesMarketplaceRights;
use Tests\TestCase;

class MarketplaceDomainTest extends TestCase
{
    use RefreshDatabase;
    use VerifiesMarketplaceRights;

    private Church $church;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('marketplace');
        Storage::fake('public');
        $this->church = Church::factory()->create();
        $this->user = User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($this->user);
    }

    public function test_global_category_item_source_and_preview_form_a_private_catalogue(): void
    {
        [$item, $source] = $this->publishedItem();
        $previewBytes = 'synthetic-preview';
        $previewKey = 'marketplace/previews/opaque/thumbnail.jpg';
        Storage::disk('marketplace')->put($previewKey, $previewBytes);
        $preview = new MarketplacePreview(['type' => MarketplacePreviewType::THUMBNAIL, 'sort_order' => 1, 'alt_text' => 'Sunday Service sample preview']);
        $preview->forceFill([
            'marketplace_item_id' => $item->id,
            'marketplace_source_version_id' => $source->id,
            'disk' => 'marketplace',
            'storage_key' => $previewKey,
            'mime_type' => 'image/jpeg',
            'size' => strlen($previewBytes),
            'width' => 600,
            'height' => 750,
            'sha256' => hash('sha256', $previewBytes),
        ])->save();

        $visible = app(MarketplaceCatalogue::class)->published();

        $this->assertCount(1, $visible);
        $this->assertSame('Sunday Service', $visible->sole()->category->name);
        $this->assertSame($preview->id, $visible->sole()->previews->sole()->id);
        $this->assertArrayNotHasKey('storage_key', $visible->sole()->previews->sole()->toArray());
        $this->assertArrayNotHasKey('church_id', $item->getAttributes());

        $response = app(MarketplacePreviewDelivery::class)->response($preview);
        $this->assertSame('noindex, nofollow, noarchive', $response->headers->get('x-robots-tag'));
        $this->assertStringContainsString('private', $response->headers->get('cache-control', ''));
    }

    public function test_source_registration_uses_private_opaque_storage_and_sha256(): void
    {
        [$item, $source, $bytes] = $this->publishedItem();
        $key = $source->getRawOriginal('storage_key');

        Storage::disk('marketplace')->assertExists($key);
        Storage::disk('public')->assertMissing($key);
        $this->assertStringStartsWith('marketplace/sources/', $key);
        $this->assertStringNotContainsString($item->slug, $key);
        $this->assertSame(hash('sha256', $bytes), $source->sha256);
        $this->assertArrayNotHasKey('storage_key', $source->toArray());
        $this->assertSame('test-marketplace-license-v1', $source->license_reference);
        $this->assertSame('publisher_declared', data_get($source->font_metadata, 'declaration_status'));
        $this->assertFalse(data_get($source->font_metadata, 'font_files_bundled'));
    }

    public function test_invalid_source_signature_is_rejected_before_storage(): void
    {
        $item = $this->item($this->category());

        try {
            app(MarketplaceSourceManager::class)->register($item, 1, 'not-a-zip', 'sample.zip');
            $this->fail('An invalid ZIP signature was accepted.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('marketplace_source_versions', 0);
            $this->assertSame([], Storage::disk('marketplace')->allFiles());
        }
    }

    public function test_slugs_and_source_version_numbers_are_bounded(): void
    {
        try {
            MarketplaceCategory::create(['name' => 'Unsafe', 'slug' => '../unsafe']);
            $this->fail('An unsafe category slug was accepted.');
        } catch (InvalidArgumentException) {
            $this->assertDatabaseCount('marketplace_categories', 0);
        }

        $item = $this->item($this->category());

        $this->expectException(ValidationException::class);
        app(MarketplaceSourceManager::class)->register($item, 0, $this->zipBytes('invalid-version'), 'sample.zip');
    }

    public function test_available_source_technical_identity_is_immutable_and_replacement_is_versioned(): void
    {
        [$item, $source] = $this->publishedItem();

        try {
            $source->forceFill(['sha256' => str_repeat('a', 64)])->save();
            $this->fail('An available source version was mutated.');
        } catch (LogicException) {
            $this->assertNotSame(str_repeat('a', 64), $source->fresh()->sha256);
        }

        $replacement = app(MarketplaceSourceManager::class)->register($item, 2, $this->zipBytes('v2'), 'sunday-service-v2.zip');
        $this->verifyMarketplaceRights($replacement);
        app(MarketplaceSourceManager::class)->makeAvailable($replacement);

        $this->assertSame([1, 2], $item->sourceVersions()->orderBy('version')->pluck('version')->all());
        $this->assertSame(2, $item->currentSourceVersion()?->version);
    }

    public function test_free_acquisition_is_church_owned_attributed_and_idempotent(): void
    {
        [$item, $source] = $this->publishedItem();

        $first = app(AcquireMarketplaceItem::class)->handle($item);
        $second = app(AcquireMarketplaceItem::class)->handle($item);

        $this->assertTrue($first->is($second));
        $this->assertSame($this->church->id, $first->church_id);
        $this->assertSame($this->user->id, $first->acquired_by);
        $this->assertSame($source->id, $first->marketplace_source_version_id);
        $this->assertDatabaseCount('marketplace_acquisitions', 1);
    }

    public function test_delivery_requires_acquisition_uses_private_bytes_and_appends_audit_events(): void
    {
        [$item, $source, $bytes] = $this->publishedItem();

        try {
            app(MarketplaceSourceDelivery::class)->issue($item);
            $this->fail('A source was delivered without an acquisition.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('marketplace_downloads', 0);
        }

        app(AcquireMarketplaceItem::class)->handle($item);
        $first = app(MarketplaceSourceDelivery::class)->issue($item);
        $second = app(MarketplaceSourceDelivery::class)->issue($item);

        ob_start();
        $first->response->sendContent();
        $delivered = ob_get_clean();

        $this->assertSame($bytes, $delivered);
        $this->assertSame($source->sha256, $first->sha256);
        $this->assertNotSame($first->downloadEventId, $second->downloadEventId);
        $this->assertSame(2, MarketplaceDownload::query()->where('outcome', MarketplaceDownloadOutcome::ISSUED)->count());
        $this->assertStringNotContainsString($source->getRawOriginal('storage_key'), $first->response->headers->get('content-disposition', ''));
    }

    public function test_premium_acquisition_is_safely_denied_without_commercial_entitlement(): void
    {
        [$item] = $this->publishedItem(MarketplaceAccessType::PREMIUM);

        $this->expectException(ValidationException::class);
        app(AcquireMarketplaceItem::class)->handle($item);
    }

    public function test_published_item_requires_a_valid_available_source_and_unpublish_blocks_delivery(): void
    {
        $category = $this->category();
        $item = $this->item($category);

        try {
            $item->publish();
            $this->fail('An item without an available source was published.');
        } catch (LogicException) {
            $this->assertSame(MarketplacePublicationStatus::DRAFT, $item->fresh()->publication_status);
        }

        [$item] = $this->publishedItem();
        app(AcquireMarketplaceItem::class)->handle($item);
        $item->unpublish();

        $this->expectException(ValidationException::class);
        app(MarketplaceSourceDelivery::class)->issue($item->fresh());
    }

    public function test_delivery_rejects_corrupted_and_withdrawn_source_packages_with_bounded_audit_failures(): void
    {
        [$item, $source, $bytes] = $this->publishedItem();
        app(AcquireMarketplaceItem::class)->handle($item);
        $key = $source->getRawOriginal('storage_key');

        Storage::disk('marketplace')->put($key, 'corrupted');

        try {
            app(MarketplaceSourceDelivery::class)->issue($item);
            $this->fail('A corrupted Marketplace package was delivered.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('marketplace_downloads', [
                'outcome' => MarketplaceDownloadOutcome::FAILED->value,
                'failure_code' => 'marketplace_source_integrity_failed',
            ]);
        }

        Storage::disk('marketplace')->put($key, $bytes);
        app(MarketplaceSourceManager::class)->withdraw($source->fresh());

        try {
            app(MarketplaceSourceDelivery::class)->issue($item);
            $this->fail('A withdrawn Marketplace package was delivered.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('marketplace_downloads', [
                'outcome' => MarketplaceDownloadOutcome::FAILED->value,
                'failure_code' => 'marketplace_source_unavailable',
            ]);
        }
    }

    /** @return array{MarketplaceItem, MarketplaceSourceVersion, string} */
    private function publishedItem(MarketplaceAccessType $accessType = MarketplaceAccessType::FREE): array
    {
        $item = $this->item($this->category(), $accessType);
        $bytes = $this->zipBytes('private-marketplace-foundation');
        $source = app(MarketplaceSourceManager::class)->register($item, 1, $bytes, 'sunday-service-sample.zip', [
            'creator_name' => 'Keryon',
            'rightsholder_name' => 'Keryon',
            'license_reference' => 'synthetic-test-only',
            'font_metadata' => [],
        ]);
        $this->verifyMarketplaceRights($source);
        app(MarketplaceSourceManager::class)->makeAvailable($source);
        $item->publish();

        return [$item->fresh(), $source->fresh(), $bytes];
    }

    private function category(): MarketplaceCategory
    {
        return MarketplaceCategory::firstOrCreate(
            ['slug' => 'sunday-service'],
            ['name' => 'Sunday Service', 'description' => 'Synthetic test category'],
        );
    }

    private function item(MarketplaceCategory $category, MarketplaceAccessType $accessType = MarketplaceAccessType::FREE): MarketplaceItem
    {
        return MarketplaceItem::create([
            'marketplace_category_id' => $category->id,
            'title' => 'Sunday Service Sample',
            'slug' => 'sunday-service-sample-'.$accessType->value.'-'.uniqid(),
            'short_description' => 'Synthetic private Marketplace test product.',
            'access_type' => $accessType,
            'publisher_name' => 'Keryon',
        ]);
    }

    private function zipBytes(string $content): string
    {
        return "PK\x03\x04".$content;
    }
}
