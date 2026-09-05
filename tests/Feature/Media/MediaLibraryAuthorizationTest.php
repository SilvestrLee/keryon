<?php

namespace Tests\Feature\Media;

use App\Enums\ChurchRole;
use App\Filament\Pages\MediaLibrary;
use App\Filament\Pages\MediaLibraryDetail;
use App\Media\IngestMediaAsset;
use App\Models\Church;
use App\Models\MediaAsset;
use App\Models\User;
use App\Support\OrganizationContext;
use App\Support\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * K-MEDIA-V1-001B §56 — the Media Library authorization test matrix
 * (items 1-11), plus the Organization/Platform-authority exclusions
 * (§49/§50) and Care route-denial (§51).
 */
class MediaLibraryAuthorizationTest extends TestCase
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

    // 1-2. Administrator can view and upload/manage.
    public function test_administrator_can_view_the_library(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->forChurch($church, [ChurchRole::ADMINISTRATOR])->create();
        $this->actingAs($user);

        $this->assertTrue(MediaLibrary::canAccess());
        Livewire::test(MediaLibrary::class)->assertSuccessful();
    }

    public function test_administrator_can_upload_and_manage(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->forChurch($church, [ChurchRole::ADMINISTRATOR])->create();
        $this->actingAs($user);

        $this->assertTrue($user->can('create', MediaAsset::class));
        $asset = $this->ingestFor($church);
        $this->assertTrue($user->can('update', $asset));
        $this->assertTrue($user->can('delete', $asset));
    }

    // 3-4. Communications can view and upload/manage.
    public function test_communications_can_view_the_library(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($user);

        $this->assertTrue(MediaLibrary::canAccess());
        Livewire::test(MediaLibrary::class)->assertSuccessful();
    }

    public function test_communications_can_upload_and_manage(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($user);

        $this->assertTrue($user->can('create', MediaAsset::class));
        $asset = $this->ingestFor($church);
        $this->assertTrue($user->can('update', $asset));
        $this->assertTrue($user->can('delete', $asset));
    }

    // 5. Care cannot view.
    public function test_care_cannot_view_the_library(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->forChurch($church, [ChurchRole::CARE])->create();
        $this->actingAs($user);

        $this->assertFalse(MediaLibrary::canAccess());
        $this->assertFalse($user->can('viewAny', MediaAsset::class));
    }

    // 6. Primary-only cannot view.
    public function test_primary_only_cannot_view_the_library(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->forChurch($church, [], primary: true)->create();
        $this->actingAs($user);

        $this->assertFalse(MediaLibrary::canAccess());
    }

    // 7. Suspended membership denied.
    public function test_suspended_membership_is_denied(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $user->memberships()->first()->suspend();
        $this->actingAs($user);

        $this->assertFalse(MediaLibrary::canAccess());
        $this->assertFalse($user->can('viewAny', MediaAsset::class));
    }

    // 8. Removed membership denied.
    public function test_removed_membership_is_denied(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $user->memberships()->first()->remove();
        $this->actingAs($user);

        $this->assertFalse(MediaLibrary::canAccess());
        $this->assertFalse($user->can('viewAny', MediaAsset::class));
    }

    // 9. OrganizationMembership alone denied.
    public function test_organization_membership_alone_grants_no_media_library_authority(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        session(['active_workspace_type' => 'organization']);
        app(OrganizationContext::class)->forgetResolved();
        app(TenantContext::class)->forgetResolved();

        $this->assertNull(app(TenantContext::class)->currentMembership());
        $this->assertFalse(MediaLibrary::canAccess());
        $this->assertFalse($user->can('viewAny', MediaAsset::class));
    }

    // 10. PlatformMembership alone denied.
    public function test_platform_membership_alone_grants_no_media_library_authority(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        session(['active_workspace_type' => 'central']);
        app(TenantContext::class)->forgetResolved();

        $this->assertNull(app(TenantContext::class)->currentMembership());
        $this->assertFalse(MediaLibrary::canAccess());
    }

    // 11. Church A cannot access Church B Media (listing, detail, mutation).
    public function test_church_a_cannot_access_church_bs_media(): void
    {
        $churchA = Church::factory()->create();
        $churchB = Church::factory()->create();
        $userA = User::factory()->forChurch($churchA, [ChurchRole::COMMUNICATIONS])->create();
        $userB = User::factory()->forChurch($churchB, [ChurchRole::COMMUNICATIONS])->create();

        $this->actingAs($userB);
        $assetB = $this->ingestFor($churchB);

        $this->actingAs($userA);
        app(TenantContext::class)->forgetResolved();

        $this->assertFalse($userA->can('view', $assetB));
        $this->assertFalse($userA->can('update', $assetB));
        $this->assertFalse($userA->can('delete', $assetB));

        $this->expectException(ModelNotFoundException::class);
        Livewire::test(MediaLibraryDetail::class, ['asset' => $assetB->uuid]);
    }

    // §51 — Care route denial (direct route, not just canAccess()).
    public function test_care_role_direct_route_is_denied(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->forChurch($church, [ChurchRole::CARE])->create();
        $this->actingAs($user);

        $response = $this->get(MediaLibrary::getUrl());
        $response->assertForbidden();
    }

    // No TenantContext at all.
    public function test_no_tenant_context_denies_media_library_access(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->assertNull(app(TenantContext::class)->currentMembership());
        $this->assertFalse(MediaLibrary::canAccess());
        $this->assertFalse($user->can('viewAny', MediaAsset::class));
    }
}
