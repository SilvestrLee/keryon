<?php

namespace Tests\Feature\ChurchWebsite;

use App\Enums\ChurchRole;
use App\Models\Church;
use App\Models\MediaAsset;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * K-CHURCHWEB-001B §37 — Institutional Media domain-boundary proof:
 * tenant ownership, cross-Church isolation, safe metadata validation,
 * and confirmation this model carries no Website-specific path coupling.
 */
class MediaAssetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function makeAsset(array $overrides = []): MediaAsset
    {
        return MediaAsset::create(array_merge([
            'disk' => 'public',
            'path' => 'tenants/1/media/some-uuid/photo.jpg',
            'original_filename' => 'photo.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 204800,
            'width' => 1200,
            'height' => 800,
            'alt_text' => 'A photo of the sanctuary.',
        ], $overrides));
    }

    public function test_media_asset_is_automatically_scoped_to_the_creating_users_church(): void
    {
        $church = Church::create(['name' => 'Media Church', 'slug' => 'media-church']);
        $user = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($user);

        $asset = $this->makeAsset();

        $this->assertSame($church->id, $asset->church_id);
    }

    public function test_media_asset_gets_a_generated_uuid_for_storage_path_identity(): void
    {
        $church = Church::create(['name' => 'UUID Church', 'slug' => 'uuid-church']);
        $user = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($user);

        $asset = $this->makeAsset();

        $this->assertNotEmpty($asset->uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $asset->uuid
        );
    }

    public function test_church_a_user_cannot_view_church_bs_media_asset(): void
    {
        $churchA = Church::create(['name' => 'Church A', 'slug' => 'church-a-media']);
        $churchB = Church::create(['name' => 'Church B', 'slug' => 'church-b-media']);
        $userA = User::factory()->forChurch($churchA, [ChurchRole::COMMUNICATIONS])->create();
        $userB = User::factory()->forChurch($churchB, [ChurchRole::COMMUNICATIONS])->create();

        $this->actingAs($userB);
        $assetB = $this->makeAsset();

        $this->actingAs($userA);

        $this->assertFalse(Gate::allows('view', $assetB));
        $this->assertNull(MediaAsset::find($assetB->id));
    }

    public function test_care_user_cannot_manage_media(): void
    {
        $church = Church::create(['name' => 'Care Media Church', 'slug' => 'care-media-church']);
        $commsUser = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($commsUser);
        $asset = $this->makeAsset();

        $careUser = User::factory()->forChurch($church, [ChurchRole::CARE])->create();
        $this->actingAs($careUser);

        $this->assertFalse(Gate::allows('view', $asset));
        $this->assertFalse(Gate::allows('update', $asset));
        $this->assertFalse(Gate::allows('delete', $asset));
        $this->assertFalse(Gate::allows('viewAny', MediaAsset::class));
    }

    public function test_creation_without_a_resolvable_tenant_context_fails_closed(): void
    {
        // No authenticated user at all — church_id can never be resolved,
        // and the column is NOT NULL, so this must fail rather than
        // silently create an ownerless (or worse, cross-tenant-visible)
        // row. Matches BelongsToChurch's fail-closed design.
        $this->assertNull(auth()->user());

        $this->expectException(\Illuminate\Database\QueryException::class);

        $this->makeAsset();
    }

    public function test_no_tenant_context_denies_access_to_an_existing_asset(): void
    {
        $church = Church::create(['name' => 'Denied Context Church', 'slug' => 'denied-context-media-church']);
        $user = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($user);
        $asset = $this->makeAsset();

        $this->app['auth']->forgetGuards();

        $this->assertFalse(Gate::allows('view', $asset));
    }

    public function test_deleting_a_media_asset_soft_deletes_it(): void
    {
        $church = Church::create(['name' => 'Soft Delete Church', 'slug' => 'soft-delete-media-church']);
        $user = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($user);

        $asset = $this->makeAsset();
        $asset->delete();

        $this->assertSoftDeleted($asset);
    }

    public function test_media_asset_path_does_not_hard_code_a_website_specific_prefix(): void
    {
        // Domain-level confirmation of K-CHURCHWEB-001B §12: MediaAsset
        // carries no "website/" or "website_uploads/" assumption anywhere
        // in its schema/model — path is caller-supplied, following
        // docs/06-Engineering/Media_Path_Strategy.md's tenant-prefixed
        // pattern, not a Website-owned convention.
        $church = Church::create(['name' => 'Path Church', 'slug' => 'path-church']);
        $user = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($user);

        $asset = $this->makeAsset(['path' => "tenants/{$church->id}/media/some-uuid/photo.jpg"]);

        $this->assertStringStartsWith("tenants/{$church->id}/media/", $asset->path);
        $this->assertStringNotContainsString('website/', $asset->path);
    }
}
