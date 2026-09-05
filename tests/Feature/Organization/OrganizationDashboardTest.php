<?php

namespace Tests\Feature\Organization;

use App\Enums\ChurchRole;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\PlatformMembershipStatus;
use App\Enums\PlatformRole;
use App\Filament\Organization\Pages\OrganizationChurchDetail;
use App\Filament\Organization\Pages\OrganizationUnitDetail;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\OrganizationUnit;
use App\Models\OrganizationUnitType;
use App\Models\PlatformMembership;
use App\Models\User;
use App\Organizations\OrganizationHierarchyService;
use App\Organizations\OrganizationIdentityService;
use App\Organizations\Read\OrganizationWorkspaceQuery;
use App\Support\OrganizationContext;
use App\Support\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrganizationDashboardTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationHierarchyService $hierarchy;

    private OrganizationIdentityService $identity;

    private Organization $organization;

    private OrganizationUnitType $type;

    private OrganizationUnit $assignedScope;

    private OrganizationUnit $descendant;

    private OrganizationUnit $sibling;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hierarchy = app(OrganizationHierarchyService::class);
        $this->identity = app(OrganizationIdentityService::class);
        $this->organization = $this->hierarchy->createOrganization('Keryon Fellowship', 'keryon-fellowship');
        $this->type = OrganizationUnitType::create([
            'organization_id' => $this->organization->id,
            'code' => 'region',
            'label' => 'Region',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $this->assignedScope = $this->unit($this->organization->rootUnit, 'Lagos Region', 'lagos');
        $this->descendant = $this->unit($this->assignedScope, 'Province 12', 'province-12');
        $this->sibling = $this->unit($this->organization->rootUnit, 'Abuja Region', 'abuja');
        Filament::setCurrentPanel(Filament::getPanel('organization'));
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        parent::tearDown();
    }

    public function test_dashboard_aggregates_and_attention_are_limited_to_authorized_scope(): void
    {
        $user = $this->organizationUser(OrganizationRole::ORGANIZATION_VIEWER, $this->assignedScope);
        $first = $this->assignedChurch($this->assignedScope, 'Mainland Church');
        $second = $this->assignedChurch($this->descendant, 'Province Church');
        $outside = $this->assignedChurch($this->sibling, 'Hidden Abuja Church');
        $this->publishedWebsite($first);
        $this->readyDomain($first);
        $this->pendingDomain($second);
        $this->pendingDomain($outside);
        $this->actingAs($user);

        $sql = [];
        DB::listen(function ($query) use (&$sql): void {
            $sql[] = strtolower($query->sql);
        });
        $dashboard = app(OrganizationWorkspaceQuery::class)->dashboard();

        $this->assertSame(2, $dashboard->churchCount);
        $this->assertSame(2, $dashboard->activeChurchCount);
        $this->assertSame(1, $dashboard->attentionCount);
        $this->assertSame(1, $dashboard->websiteLiveCount);
        $this->assertSame(1, $dashboard->websiteAttentionCount);
        $this->assertSame(1, $dashboard->domainConnectedCount);
        $this->assertSame(1, $dashboard->domainAttentionCount);
        $this->assertSame(0, $dashboard->keryonAddressCount);
        $this->assertNull($dashboard->peopleCount);
        $this->assertSame(['Province Church'], collect($dashboard->attentionItems)->pluck('name')->all());
        $this->assertSame('Keryon Fellowship → Lagos Region', $dashboard->scopes[0]['path']);
        $this->assertFalse(app(TenantContext::class)->hasContext());

        $executed = implode("\n", $sql);
        foreach (['prayer_requests', 'congregation_members', 'content_items', 'media_assets'] as $privateTable) {
            $this->assertStringNotContainsString($privateTable, $executed);
        }

        $response = $this->get('/organization/organization-overview');
        $this->assertSame(200, $response->status(), $response->getContent());
        $response
            ->assertSee('1 Church needs attention')
            ->assertSee($second->name)
            ->assertSee('1 live; 1 not published')
            ->assertDontSee($outside->name)
            ->assertSee('Nothing needs attention')
            ->assertSee('No Organization campaigns are in progress');
    }

    public function test_zero_data_dashboard_keeps_the_complete_workspace_information_architecture_visible(): void
    {
        $emptyOrganization = $this->hierarchy->createOrganization('Ready Organization', 'ready-organization');
        $user = User::factory()->create();
        $this->identity->bootstrapAdministrator($emptyOrganization, $user);
        $this->actingAs($user);

        $dashboard = app(OrganizationWorkspaceQuery::class)->dashboard();
        $this->assertSame(0, $dashboard->churchCount);
        $this->assertSame(0, $dashboard->unitCount);
        $this->assertSame([], $dashboard->churchPreview);
        $this->assertSame([], $dashboard->unitPreview);
        $this->assertSame([], $dashboard->communicationItems);
        $this->assertSame([], $dashboard->campaignItems);

        $response = $this->get('/organization/organization-overview');
        $this->assertSame(200, $response->status(), $response->getContent());
        $response
            ->assertSee('No immediate website or setup issues')
            ->assertSee('Organization network')
            ->assertSee('No Churches are assigned yet')
            ->assertSee('No Units exist below the root')
            ->assertSee('Publishing state')
            ->assertSee('Church addresses')
            ->assertSee('Organization communication')
            ->assertSee('Communications')
            ->assertSee('Campaigns')
            ->assertSee('Authorized organization scope')
            ->assertSee('People &amp; access', escape: false)
            ->assertSee('Organization governance');
    }

    public function test_church_and_unit_detail_routes_fail_closed_outside_assigned_scope_and_organization(): void
    {
        $user = $this->organizationUser(OrganizationRole::ORGANIZATION_VIEWER, $this->assignedScope);
        $insideChurch = $this->assignedChurch($this->descendant, 'Visible Church');
        $siblingChurch = $this->assignedChurch($this->sibling, 'Sibling Church');
        $other = $this->hierarchy->createOrganization('Other Organization', 'other-organization');
        $otherType = OrganizationUnitType::create([
            'organization_id' => $other->id,
            'code' => 'province',
            'label' => 'Province',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $otherUnit = $this->hierarchy->createUnit($other, $otherType, $other->rootUnit, ['name' => 'Other Province', 'code' => 'other-province']);
        $otherChurch = $this->assignedChurchFor($other, $otherUnit, 'Other Church');
        $this->actingAs($user);

        $this->get(OrganizationChurchDetail::getUrl(['record' => $insideChurch->id], panel: 'organization'))
            ->assertOk()
            ->assertSee('Visible Church')
            ->assertSee('Church authority remains independent');
        $this->get(OrganizationUnitDetail::getUrl(['record' => $this->descendant->id], panel: 'organization'))
            ->assertOk()
            ->assertSee('Province 12');

        $this->get(OrganizationChurchDetail::getUrl(['record' => $siblingChurch->id], panel: 'organization'))->assertNotFound();
        $this->get(OrganizationUnitDetail::getUrl(['record' => $this->sibling->id], panel: 'organization'))->assertNotFound();
        $this->get(OrganizationChurchDetail::getUrl(['record' => $otherChurch->id], panel: 'organization'))->assertNotFound();
        $this->get(OrganizationUnitDetail::getUrl(['record' => $otherUnit->id], panel: 'organization'))->assertNotFound();
    }

    public function test_scoped_directory_is_searchable_paginated_and_does_not_eager_load_the_tree(): void
    {
        $user = $this->organizationUser(OrganizationRole::ORGANIZATION_VIEWER, $this->assignedScope);
        foreach (range(1, 23) as $number) {
            $this->assignedChurch($this->descendant, sprintf('Scoped Church %02d', $number));
        }
        $this->assignedChurch($this->sibling, 'Never Visible Church');
        $this->actingAs($user);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $page = app(OrganizationWorkspaceQuery::class)->paginateChurches(perPage: 10);
        $queries = DB::getQueryLog();

        $this->assertSame(23, $page->total());
        $this->assertCount(10, $page->items());
        $this->assertLessThanOrEqual(12, count($queries));
        $this->assertSame(1, app(OrganizationWorkspaceQuery::class)->paginateChurches('Scoped Church 17')->total());
        $this->assertSame(0, app(OrganizationWorkspaceQuery::class)->paginateChurches('Never Visible Church')->total());
    }

    public function test_suspended_removed_church_only_and_platform_only_users_cannot_open_dashboard_routes(): void
    {
        $churchOnly = User::factory()->create();
        ChurchMembership::createPrimary(Church::factory()->create(), $churchOnly, [ChurchRole::ADMINISTRATOR]);
        $platformOnly = User::factory()->create();
        PlatformMembership::create([
            'user_id' => $platformOnly->id,
            'role' => PlatformRole::SUPPORT,
            'status' => PlatformMembershipStatus::ACTIVE,
            'activated_at' => now(),
        ]);

        foreach ([$churchOnly, $platformOnly] as $user) {
            $this->actingAs($user);
            app(OrganizationContext::class)->forgetResolved();
            $this->get('/organization/organization-overview')->assertForbidden();
        }

        foreach ([OrganizationMembershipStatus::SUSPENDED, OrganizationMembershipStatus::REMOVED] as $status) {
            $user = User::factory()->create();
            $membership = OrganizationMembership::create([
                'organization_id' => $this->organization->id,
                'user_id' => $user->id,
                'status' => $status,
                'joined_at' => now(),
                'suspended_at' => $status === OrganizationMembershipStatus::SUSPENDED ? now() : null,
                'removed_at' => $status === OrganizationMembershipStatus::REMOVED ? now() : null,
            ]);
            $this->actingAs($user);
            app(OrganizationContext::class)->forgetResolved();
            $this->get('/organization/organization-overview')->assertForbidden();
            $this->assertSame($status, $membership->fresh()->status);
        }
    }

    private function unit(OrganizationUnit $parent, string $name, string $code): OrganizationUnit
    {
        return $this->hierarchy->createUnit($this->organization, $this->type, $parent, compact('name', 'code'));
    }

    private function organizationUser(OrganizationRole $role, OrganizationUnit $unit): User
    {
        $user = User::factory()->create();
        $membership = $this->identity->invite($this->organization, $user);
        $membership = $this->identity->activate($membership);
        $this->identity->assignRole($membership, $role, $unit);

        return $user;
    }

    private function assignedChurch(OrganizationUnit $unit, string $name): Church
    {
        return $this->assignedChurchFor($this->organization, $unit, $name);
    }

    private function assignedChurchFor(Organization $organization, OrganizationUnit $unit, string $name): Church
    {
        $church = Church::factory()->create(['name' => $name]);
        $primary = User::factory()->create();
        ChurchMembership::createPrimary($church, $primary, [ChurchRole::ADMINISTRATOR]);
        $pending = $this->hierarchy->attachChurch($organization, $unit, $church);
        $this->hierarchy->acceptAttachment($pending, $primary);

        return $church->fresh();
    }

    private function pendingDomain(Church $church): void
    {
        DB::table('church_domains')->insert([
            'uuid' => fake()->uuid(),
            'church_id' => $church->id,
            'normalized_hostname' => $church->slug.'.example.test',
            'display_hostname' => $church->slug.'.example.test',
            'status' => 'pending_verification',
            'verification_method' => 'txt',
            'verification_token_hash' => hash('sha256', $church->slug),
            'tls_status' => 'not_started',
            'is_primary' => true,
            'consecutive_failures' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function readyDomain(Church $church): void
    {
        DB::table('church_domains')->insert([
            'uuid' => fake()->uuid(),
            'church_id' => $church->id,
            'normalized_hostname' => $church->slug.'.example.test',
            'display_hostname' => $church->slug.'.example.test',
            'status' => 'active',
            'verification_method' => 'txt',
            'verification_token_hash' => hash('sha256', $church->slug),
            'ownership_verified_at' => now(),
            'routing_verified_at' => now(),
            'tls_status' => 'ready',
            'tls_ready_at' => now(),
            'is_primary' => true,
            'consecutive_failures' => 0,
            'activated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function publishedWebsite(Church $church): void
    {
        $publication = DB::table('website_publications')->insertGetId([
            'church_id' => $church->id,
            'theme' => 'proclaim',
            'snapshot' => '{}',
            'working_fingerprint' => str_repeat('a', 64),
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('website_settings')->insert([
            'church_id' => $church->id,
            'theme' => 'proclaim',
            'current_publication_id' => $publication,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
