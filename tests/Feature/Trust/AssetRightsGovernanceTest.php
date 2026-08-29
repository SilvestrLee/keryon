<?php

namespace Tests\Feature\Trust;

use App\Enums\AssetProvenance;
use App\Enums\AssetRightsStatus;
use App\Enums\AssetUse;
use App\Enums\ChurchRole;
use App\Enums\MarketplaceAccessType;
use App\Filament\Support\MediaSelectField;
use App\Marketplace\MarketplaceSourceManager;
use App\Models\Church;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceItem;
use App\Models\MediaAsset;
use App\Models\MediaAssetRights;
use App\Models\User;
use App\Models\WebsiteHomeContent;
use App\Models\WebsiteSettings;
use App\PublicWebsite\WebsitePublisher;
use App\Support\TenantContext;
use App\Trust\Rights\AssetRightsPolicy;
use App\Trust\Rights\RecordMediaAssetRights;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\VerifiesMarketplaceRights;
use Tests\TestCase;

class AssetRightsGovernanceTest extends TestCase
{
    use RefreshDatabase;
    use VerifiesMarketplaceRights;

    private Church $church;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media-private');
        Storage::fake('media-public');
        Storage::fake('marketplace');
        $this->church = Church::factory()->create(['slug' => 'rights-church']);
        $this->user = User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($this->user);
        WebsiteSettings::create();
    }

    public function test_ordinary_church_upload_records_low_friction_operational_rights_only(): void
    {
        $staging = "tenants/{$this->church->id}/media/.staging/rights-upload.tmp";
        Storage::disk('media-private')->put($staging, $this->png());

        $asset = MediaSelectField::ingest($staging, 'pastor.png');
        $rights = $asset->rights()->sole();
        $policy = app(AssetRightsPolicy::class);

        $this->assertSame(AssetProvenance::ChurchDeclared, $rights->provenance);
        $this->assertSame(AssetRightsStatus::Declared, $rights->status);
        $this->assertTrue($policy->allows($asset, AssetUse::Store));
        $this->assertTrue($policy->allows($asset, AssetUse::InternalUse));
        $this->assertTrue($policy->allows($asset, AssetUse::Publish));
        $this->assertFalse($policy->allows($asset, AssetUse::AiProcess));
        $this->assertFalse($policy->allows($asset, AssetUse::Reference));
        $this->assertFalse($policy->allows($asset, AssetUse::Train));
        $this->assertFalse($policy->allows($asset, AssetUse::CommercialUse));
        $this->assertFalse($policy->allows($asset, AssetUse::Redistribute));
    }

    public function test_reference_permission_does_not_imply_training_or_ai_permission(): void
    {
        $asset = $this->asset();
        MediaAssetRights::create([
            'church_id' => $this->church->id,
            'media_asset_id' => $asset->id,
            'provenance' => AssetProvenance::LicensedThirdParty,
            'status' => AssetRightsStatus::Verified,
            'allowed_uses' => [AssetUse::Store->value, AssetUse::Reference->value],
            'declared_at' => now(),
        ]);

        $policy = app(AssetRightsPolicy::class);
        $this->assertTrue($policy->allows($asset->fresh(), AssetUse::Reference));
        $this->assertFalse($policy->allows($asset->fresh(), AssetUse::Train));
        $this->assertFalse($policy->allows($asset->fresh(), AssetUse::AiProcess));
        $this->assertFalse($policy->allows($asset->fresh(), AssetUse::Publish));
    }

    public function test_disputed_media_cannot_newly_publish_and_existing_public_reference_is_deactivated(): void
    {
        $asset = $this->asset();
        app(RecordMediaAssetRights::class)->churchDeclared($asset);
        WebsiteHomeContent::create(['hero_image_id' => $asset->id]);
        $publication = app(WebsitePublisher::class)->publish();
        $renditionUuid = $publication->snapshot['public_media']['home.hero'];

        app(RecordMediaAssetRights::class)->restrict($asset, AssetRightsStatus::Disputed, 'Publication authority is disputed.');

        $this->get(route('media.public', $renditionUuid))->assertNotFound();

        try {
            app(WebsitePublisher::class)->publish();
            $this->fail('Disputed media was published.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('media', $exception->errors());
        }

        Storage::disk('media-private')->assertExists($asset->path);
    }

    public function test_another_church_cannot_inspect_or_change_media_rights(): void
    {
        $asset = $this->asset();
        app(RecordMediaAssetRights::class)->churchDeclared($asset);
        $rightsId = $asset->rights()->sole()->id;

        $other = Church::factory()->create();
        $this->actingAs(User::factory()->forChurch($other, [ChurchRole::COMMUNICATIONS])->create());
        app(TenantContext::class)->forgetResolved();

        $this->assertNull(MediaAssetRights::find($rightsId));
        $this->expectException(AuthorizationException::class);
        app(RecordMediaAssetRights::class)->restrict($asset, AssetRightsStatus::Withdrawn, 'Uploader withdrew authority.');
    }

    public function test_legacy_media_gets_only_the_bounded_operational_default(): void
    {
        $asset = $this->asset();
        $policy = app(AssetRightsPolicy::class);

        $this->assertTrue($policy->allows($asset, AssetUse::Publish));
        $this->assertFalse($policy->allows($asset, AssetUse::AiProcess));
        $this->assertFalse($policy->allows($asset, AssetUse::Redistribute));
        $this->assertDatabaseMissing('media_asset_rights', ['media_asset_id' => $asset->id]);
    }

    public function test_marketplace_policy_delegates_to_version_specific_verified_clearance(): void
    {
        $category = MarketplaceCategory::create(['name' => 'Services', 'slug' => 'services']);
        $item = MarketplaceItem::create([
            'marketplace_category_id' => $category->id,
            'title' => 'Versioned rights proof',
            'slug' => 'versioned-rights-proof',
            'short_description' => 'Synthetic source.',
            'access_type' => MarketplaceAccessType::FREE,
            'publisher_name' => 'Keryon',
        ]);
        $first = app(MarketplaceSourceManager::class)->register($item, 1, "PK\x03\x04first", 'first.zip');
        $first = $this->verifyMarketplaceRights($first);
        $second = app(MarketplaceSourceManager::class)->register($item, 2, "PK\x03\x04replacement", 'second.zip');
        $policy = app(AssetRightsPolicy::class);

        $this->assertTrue($policy->allows($first, AssetUse::Redistribute));
        $this->assertTrue($policy->allows($first, AssetUse::CommercialUse));
        $this->assertFalse($policy->allows($first, AssetUse::AiProcess));
        $this->assertFalse($policy->allows($second, AssetUse::Redistribute));
    }

    private function asset(): MediaAsset
    {
        $uuid = (string) Str::uuid();
        $path = "tenants/{$this->church->id}/media/{$uuid}/original.png";
        Storage::disk('media-private')->put($path, $this->png());

        $asset = new MediaAsset([
            'disk' => 'media-private',
            'path' => $path,
            'original_filename' => 'church.png',
            'mime_type' => 'image/png',
            'size' => strlen($this->png()),
            'sha256' => hash('sha256', $this->png()),
            'width' => 1,
            'height' => 1,
        ]);
        $asset->forceFill(['church_id' => $this->church->id, 'uuid' => $uuid])->save();

        return $asset;
    }

    private function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    }
}
