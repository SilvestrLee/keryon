<?php

namespace Tests\Feature\Shell;

use App\Commercial\Entitlements\EntitlementResolver;
use App\Enums\ChurchRole;
use App\Enums\ContentType;
use App\Enums\EntitlementKey;
use App\Enums\MembershipStatus;
use App\Enums\WorkspaceType;
use App\Livewire\KeryonWorkspaceHeader;
use App\Localization\UserLocaleResolver;
use App\Models\Campaign;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\ContentItem;
use App\Models\User;
use App\Models\WebsitePublication;
use App\Models\WebsiteSettings;
use App\Organizations\OrganizationHierarchyService;
use App\Organizations\OrganizationIdentityService;
use App\Search\GlobalSearchService;
use App\Support\OrganizationContext;
use App\Support\TenantContext;
use App\Website\ChurchPublicUrlResolver;
use App\Workspace\WorkspaceRegistry;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class WorkspaceShellTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_registry_lists_only_real_active_memberships_and_marks_current_workspace(): void
    {
        $first = Church::factory()->create(['name' => 'Salvation Center']);
        $second = Church::factory()->create(['name' => 'Grace Hall']);
        $inactive = Church::factory()->create(['name' => 'Inactive Hall', 'is_active' => false]);
        $user = User::factory()->forChurch($first, [ChurchRole::ADMINISTRATOR])->create();
        User::factory()->forChurch($second, [ChurchRole::COMMUNICATIONS])->create(['email' => 'unused@example.test']);
        ChurchMembership::factory()->for($second)->for($user)->create();
        ChurchMembership::factory()->for($inactive)->for($user)->create();
        $suspended = Church::factory()->create(['name' => 'Suspended Church']);
        ChurchMembership::factory()->for($suspended)->for($user)->create(['status' => MembershipStatus::SUSPENDED]);
        $this->actingAs($user)->withSession(['active_church_id' => $second->id]);

        $options = app(WorkspaceRegistry::class)->for($user, WorkspaceType::Church);

        $this->assertEqualsCanonicalizing(['Salvation Center', 'Grace Hall'], $options->pluck('name')->all());
        $this->assertTrue($options->firstWhere('id', $second->id)->current);
        $this->assertFalse($options->firstWhere('id', $first->id)->current);
    }

    public function test_switching_authority_planes_revalidates_membership_and_clears_incompatible_context(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->forChurch($church, [ChurchRole::ADMINISTRATOR])->create();
        $organization = app(OrganizationHierarchyService::class)->createOrganization('NTGCI International', 'ntgci');
        app(OrganizationIdentityService::class)->bootstrapAdministrator($organization, $user);

        $this->actingAs($user)->withSession(['active_church_id' => $church->id])
            ->post(route('workspace.switch', ['type' => 'organization', 'workspace' => $organization->id]))
            ->assertRedirect('/organization');
        $this->assertFalse(session()->has('active_church_id'));
        $this->assertSame($organization->id, session('active_organization_id'));
        $this->assertFalse(app(TenantContext::class)->hasContext());

        $this->post(route('workspace.switch', ['type' => 'church', 'workspace' => $church->id]))
            ->assertRedirect('/admin');
        $this->assertFalse(session()->has('active_organization_id'));
        $this->assertSame($church->id, app(TenantContext::class)->currentChurchId());

        $this->post(route('workspace.switch', ['type' => 'church', 'workspace' => 999999]))->assertForbidden();
    }

    public function test_organization_scope_never_manufactures_a_church_workspace_option(): void
    {
        $organization = app(OrganizationHierarchyService::class)->createOrganization('Regional Body', 'regional');
        $user = User::factory()->create();
        app(OrganizationIdentityService::class)->bootstrapAdministrator($organization, $user);
        $governedChurch = Church::factory()->create();
        $primary = User::factory()->create();
        ChurchMembership::createPrimary($governedChurch, $primary);
        $attachment = app(OrganizationHierarchyService::class)->attachChurch($organization, $organization->rootUnit, $governedChurch);
        app(OrganizationHierarchyService::class)->acceptAttachment($attachment, $primary);
        $this->actingAs($user);

        $options = app(WorkspaceRegistry::class)->for($user, WorkspaceType::Organization);

        $this->assertSame(['organization'], $options->pluck('type.value')->unique()->values()->all());
        $this->post(route('workspace.switch', ['type' => 'church', 'workspace' => $governedChurch->id]))->assertForbidden();
    }

    public function test_church_search_is_capability_and_tenant_bounded_with_grouped_results(): void
    {
        $church = Church::factory()->create();
        $other = Church::factory()->create();
        $user = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($user)->withSession(['active_church_id' => $church->id]);
        app(TenantContext::class)->forgetResolved();
        ContentItem::create(['title' => 'Easter Announcement', 'content_type' => ContentType::ANNOUNCEMENT, 'body' => 'Church copy']);
        ContentItem::withoutEvents(function () use ($other): void {
            $item = new ContentItem(['title' => 'Easter Private Other Church', 'content_type' => ContentType::ANNOUNCEMENT, 'body' => 'Private']);
            $item->church_id = $other->id;
            $item->save();
        });
        Campaign::create(['title' => 'Easter Conference']);

        $groups = app(GlobalSearchService::class)->search(WorkspaceType::Church, 'Easter');

        $this->assertSame(['Content', 'Campaigns'], $groups->keys()->all());
        $this->assertTrue($groups->flatten(1)->contains(fn ($result) => $result->title === 'Easter Announcement'));
        $this->assertFalse($groups->flatten(1)->contains(fn ($result) => str_contains($result->title, 'Other Church')));
        $this->assertLessThanOrEqual(12, $groups->flatten(1)->count());
    }

    public function test_care_provider_is_not_executed_without_care_view(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($user);
        app(TenantContext::class)->forgetResolved();
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        app(GlobalSearchService::class)->search(WorkspaceType::Church, 'prayer');

        $this->assertFalse(collect($queries)->contains(fn (string $sql) => str_contains($sql, 'prayer_requests')));
    }

    public function test_organization_search_uses_sql_scope_and_never_queries_operational_content_or_care(): void
    {
        $hierarchy = app(OrganizationHierarchyService::class);
        $organization = $hierarchy->createOrganization('International Body', 'international');
        $user = User::factory()->create();
        app(OrganizationIdentityService::class)->bootstrapAdministrator($organization, $user);
        $this->actingAs($user);
        app(OrganizationContext::class)->forgetResolved();
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $groups = app(GlobalSearchService::class)->search(WorkspaceType::Organization, 'International');

        $this->assertTrue($groups->has('Organization units'));
        $this->assertFalse(collect($queries)->contains(fn (string $sql) => str_contains($sql, 'content_items') || str_contains($sql, 'prayer_requests')));
        $this->assertTrue(collect($queries)->contains(fn (string $sql) => str_contains($sql, 'organization_unit_paths')));
    }

    public function test_locale_is_user_owned_bounded_and_does_not_mutate_church_or_timezone(): void
    {
        $church = Church::factory()->create(['timezone' => 'Africa/Lagos']);
        $first = User::factory()->forChurch($church, [ChurchRole::ADMINISTRATOR])->create();
        $second = User::factory()->create();
        $this->actingAs($first);

        app(UserLocaleResolver::class)->select($first, 'en');

        $this->assertSame('en', $first->fresh()->locale);
        $this->assertNull($second->fresh()->locale);
        $this->assertSame('Africa/Lagos', $church->fresh()->timezone);
        $this->expectException(ValidationException::class);
        app(UserLocaleResolver::class)->select($first, '../../fr');
    }

    public function test_public_url_resolver_requires_entitlement_and_a_real_current_publication(): void
    {
        $church = Church::factory()->create(['slug' => 'salvation-center']);
        $user = User::factory()->forChurch($church, [ChurchRole::ADMINISTRATOR])->create();
        $this->actingAs($user);
        app(TenantContext::class)->forgetResolved();
        $entitlements = Mockery::mock(EntitlementResolver::class);
        $entitlements->shouldReceive('allows')->with(Mockery::type(Church::class), EntitlementKey::WebsiteEnabled)->andReturnTrue();
        $this->app->instance(EntitlementResolver::class, $entitlements);
        WebsiteSettings::create([]);

        $this->assertNull(app(ChurchPublicUrlResolver::class)->resolve($church));

        $publication = WebsitePublication::withoutEvents(function () use ($church, $user): WebsitePublication {
            $record = new WebsitePublication([
                'destination' => 'church_website', 'theme' => 'proclaim', 'snapshot' => [],
                'working_fingerprint' => str_repeat('a', 64), 'trust_evidence' => [],
                'published_by' => $user->id, 'published_at' => now(),
            ]);
            $record->church_id = $church->id;
            $record->save();

            return $record;
        });
        WebsiteSettings::first()->update(['current_publication_id' => $publication->id]);

        $this->assertSame('https://salvation-center.keryon.app', app(ChurchPublicUrlResolver::class)->resolve($church));
    }

    public function test_header_search_has_accessible_minimum_empty_and_grouped_states(): void
    {
        $church = Church::factory()->create(['name' => 'A Church Name That Remains Clear']);
        $user = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($user);
        app(TenantContext::class)->forgetResolved();
        $entitlements = Mockery::mock(EntitlementResolver::class);
        $entitlements->shouldReceive('allows')->andReturnFalse();
        $this->app->instance(EntitlementResolver::class, $entitlements);
        ContentItem::create(['title' => 'Sunday Announcement', 'content_type' => ContentType::ANNOUNCEMENT, 'body' => 'Copy']);

        Livewire::test(KeryonWorkspaceHeader::class)
            ->assertSee('A Church Name That Remains Clear')
            ->assertSee('Church workspace')
            ->call('openSearch')
            ->assertSee('Enter at least 2 characters.')
            ->set('query', 'zzzz')
            ->assertSee('No results found for &quot;zzzz&quot;.', escape: false)
            ->set('query', 'Sunday')
            ->assertSee('Content')
            ->assertSee('Sunday Announcement');
    }

    public function test_header_and_search_query_counts_are_bounded_for_multiple_workspaces(): void
    {
        $first = Church::factory()->create();
        $second = Church::factory()->create();
        $user = User::factory()->forChurch($first, [ChurchRole::ADMINISTRATOR])->create();
        ChurchMembership::factory()->for($second)->for($user)->create();
        $this->actingAs($user)->withSession(['active_church_id' => $first->id]);
        app(TenantContext::class)->forgetResolved();
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $component = Livewire::test(KeryonWorkspaceHeader::class);
        $baseHeaderQueries = count($queries);
        $queries = [];
        $component->call('openSearch')->set('query', 'member');

        $this->assertLessThanOrEqual(24, count($queries));
        $this->assertLessThanOrEqual(10, $baseHeaderQueries);
        $this->assertFalse(collect($queries)->contains(fn (string $sql) => str_contains($sql, 'prayer_requests')));
    }
}
