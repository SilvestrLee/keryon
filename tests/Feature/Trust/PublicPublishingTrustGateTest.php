<?php

namespace Tests\Feature\Trust;

use App\Enums\AssetRightsStatus;
use App\Enums\ChurchRole;
use App\Enums\PublicationAiReviewStatus;
use App\Enums\PublicationDestination;
use App\Media\PublicMediaRenditionManager;
use App\Models\Church;
use App\Models\MediaAsset;
use App\Models\User;
use App\Models\WebsiteHomeContent;
use App\Models\WebsiteSettings;
use App\PublicWebsite\WebsitePublicationHealth;
use App\PublicWebsite\WebsitePublisher;
use App\Trust\Publishing\PublicationTrustContext;
use App\Trust\Publishing\PublicationTrustGate;
use App\Trust\Rights\RecordMediaAssetRights;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class PublicPublishingTrustGateTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    private User $publisher;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media-private');
        Storage::fake('media-public');
        Storage::fake('public');
        $this->church = Church::factory()->create(['slug' => 'publishing-trust']);
        $this->publisher = User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($this->publisher);
        WebsiteSettings::create();
    }

    public function test_explicit_website_publish_records_bounded_immutable_trust_evidence(): void
    {
        $asset = $this->asset();
        app(RecordMediaAssetRights::class)->churchDeclared($asset);
        WebsiteHomeContent::create(['hero_heading' => 'Public by explicit action', 'hero_image_id' => $asset->id]);

        $first = app(WebsitePublisher::class)->publish();
        $second = app(WebsitePublisher::class)->publish();

        $this->assertSame(PublicationDestination::ChurchWebsite, $first->destination);
        $this->assertSame($this->church->id, $first->church_id);
        $this->assertSame($this->publisher->id, $first->published_by);
        $this->assertNotNull($first->published_at);
        $this->assertNull($first->previous_publication_id);
        $this->assertSame($first->id, $second->previous_publication_id);
        $this->assertTrue($first->trust_evidence['explicit_public_intent']);
        $this->assertSame('passed', $first->trust_evidence['asset_rights_evaluation']);
        $this->assertSame('unknown', $first->trust_evidence['ai_review_status']);
        $this->assertSame($first->snapshot['public_media'], $first->trust_evidence['renditions']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $first->working_fingerprint);
        $this->assertStringNotContainsString($asset->path, json_encode($first->snapshot, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString($asset->uuid, json_encode($first->snapshot, JSON_THROW_ON_ERROR));
        $this->assertArrayNotHasKey('hero_image_id', $first->snapshot['home']);
        $this->assertSame('healthy', app(WebsitePublicationHealth::class)->evaluate($second)['state']);

        $this->expectException(LogicException::class);
        $first->update(['theme' => 'changed']);
    }

    public function test_restricted_disputed_and_withdrawn_assets_prevent_replacement_and_preserve_live_version(): void
    {
        foreach ([AssetRightsStatus::Restricted, AssetRightsStatus::Disputed, AssetRightsStatus::Withdrawn] as $status) {
            $asset = $this->asset();
            app(RecordMediaAssetRights::class)->churchDeclared($asset);
            $home = WebsiteHomeContent::query()->firstOrCreate([], ['hero_heading' => 'Stable public version']);
            $home->update(['hero_image_id' => $asset->id]);
            $live = app(WebsitePublisher::class)->publish();

            app(RecordMediaAssetRights::class)->restrict($asset, $status, 'Publication authority no longer applies.');

            try {
                app(WebsitePublisher::class)->publish();
                $this->fail("{$status->value} media produced a replacement publication.");
            } catch (ValidationException) {
                $this->assertSame($live->id, WebsiteSettings::query()->sole()->current_publication_id);
                $this->assertSame('degraded', app(WebsitePublicationHealth::class)->evaluate($live)['state']);
                Storage::disk('media-private')->assertExists($asset->path);
            }

            app(WebsitePublisher::class)->unpublish();
        }
    }

    public function test_public_schema_rejects_care_or_internal_fields_and_unreviewed_ai(): void
    {
        $membership = $this->publisher->memberships()->where('church_id', $this->church->id)->firstOrFail();
        $base = [
            'church' => ['name' => $this->church->name, 'slug' => $this->church->slug],
            'brand' => null,
            'settings' => ['footer_note' => null],
            'home' => null,
            'about' => null,
            'contact' => null,
            'leadership' => [],
            'ministries' => [],
            'service_times' => [],
            'social_links' => [],
            'public_media' => [],
        ];

        try {
            app(PublicationTrustGate::class)->evaluate(new PublicationTrustContext(
                PublicationDestination::ChurchWebsite,
                $membership,
                $this->church,
                [...$base, 'care_notes' => ['private pastoral note']],
                [],
                [],
            ));
            $this->fail('Care-shaped data entered the public schema.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('publication', $exception->errors());
        }

        $this->expectException(ValidationException::class);
        app(PublicationTrustGate::class)->evaluate(new PublicationTrustContext(
            PublicationDestination::ChurchWebsite,
            $membership,
            $this->church,
            $base,
            [],
            [],
            PublicationAiReviewStatus::Unreviewed,
        ));
    }

    public function test_cross_church_rendition_cannot_be_injected_into_publication_context(): void
    {
        $asset = $this->asset();
        app(RecordMediaAssetRights::class)->churchDeclared($asset);
        $rendition = app(PublicMediaRenditionManager::class)->rendition($asset, $this->church->id);

        $other = Church::factory()->create();
        $otherUser = User::factory()->forChurch($other, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($otherUser);
        $membership = $otherUser->memberships()->where('church_id', $other->id)->firstOrFail();
        $snapshot = [
            'church' => ['name' => $other->name, 'slug' => $other->slug],
            'brand' => null,
            'settings' => ['footer_note' => null],
            'home' => null,
            'about' => null,
            'contact' => null,
            'leadership' => [],
            'ministries' => [],
            'service_times' => [],
            'social_links' => [],
            'public_media' => ['home.hero' => $rendition->uuid],
        ];

        $this->expectException(ValidationException::class);
        app(PublicationTrustGate::class)->evaluate(new PublicationTrustContext(
            PublicationDestination::ChurchWebsite,
            $membership,
            $other,
            $snapshot,
            ['home.hero' => $asset->id],
            ['home.hero' => $rendition->uuid],
        ));
    }

    public function test_unpublish_preserves_working_content_history_and_canonical_media(): void
    {
        $asset = $this->asset();
        app(RecordMediaAssetRights::class)->churchDeclared($asset);
        WebsiteHomeContent::create(['hero_heading' => 'Preserved working content', 'hero_image_id' => $asset->id]);
        $publication = app(WebsitePublisher::class)->publish();

        app(WebsitePublisher::class)->unpublish();

        $this->assertDatabaseHas('website_publications', ['id' => $publication->id]);
        $this->assertSame('Preserved working content', WebsiteHomeContent::query()->sole()->hero_heading);
        $this->assertNotNull($publication->publicReferences()->sole()->deactivated_at);
        Storage::disk('media-private')->assertExists($asset->path);
        $this->get('http://publishing-trust.keryon.app')->assertNotFound();
    }

    private function asset(): MediaAsset
    {
        $uuid = (string) Str::uuid();
        $path = "tenants/{$this->church->id}/media/{$uuid}/original.png";
        Storage::disk('media-private')->put($path, $this->png());

        $asset = new MediaAsset([
            'disk' => 'media-private',
            'path' => $path,
            'original_filename' => 'publication.png',
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
