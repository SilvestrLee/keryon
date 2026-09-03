<?php

namespace Tests\Feature\Organization;

use App\Enums\OrganizationCommunicationKind;
use App\Enums\OrganizationCommunicationMaterialType;
use App\Filament\Organization\Pages\OrganizationCommunicationDetail;
use App\Filament\Organization\Pages\OrganizationCommunications;
use App\Models\Organization;
use App\Models\OrganizationUnit;
use App\Models\OrganizationUnitType;
use App\Models\User;
use App\Organizations\Communications\OrganizationCommunicationManager;
use App\Organizations\Communications\OrganizationCommunicationWorkflow;
use App\Organizations\OrganizationHierarchyService;
use App\Organizations\OrganizationIdentityService;
use App\Support\OrganizationContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * K-ORG-COMMS-001B — actually renders the two new Filament pages
 * end-to-end (not just the underlying services), the way manual/browser
 * verification does. Added after discovering, via manual browser
 * testing, that `OrganizationCommunicationQuery::auditEvents()` crashed
 * on any communication with at least one audit event (every
 * communication has one immediately after creation) because
 * `Collection::map()` downgrades an Eloquent Collection to a base
 * Support Collection once its items stop being models — a class of bug
 * pure service-layer tests never exercise, since they never render the
 * Blade view that calls it.
 */
class OrganizationCommunicationPageTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationHierarchyService $hierarchy;

    private OrganizationIdentityService $identity;

    private OrganizationCommunicationManager $manager;

    private Organization $organization;

    private OrganizationUnit $scope;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('organization'));

        $this->hierarchy = app(OrganizationHierarchyService::class);
        $this->identity = app(OrganizationIdentityService::class);
        $this->manager = app(OrganizationCommunicationManager::class);

        $this->organization = $this->hierarchy->createOrganization('Render Network', 'render-network');
        $type = OrganizationUnitType::query()->create([
            'organization_id' => $this->organization->id,
            'code' => 'region',
            'label' => 'Region',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $this->scope = $this->hierarchy->createUnit($this->organization, $type, $this->organization->rootUnit, [
            'name' => 'Lagos Region',
            'code' => 'lagos',
        ]);

        $this->admin = User::factory()->create();
        $this->identity->bootstrapAdministrator($this->organization, $this->admin);
        $this->actingAs($this->admin);
        session(['active_workspace_type' => 'organization', 'active_organization_id' => $this->organization->id]);
        app(OrganizationContext::class)->forgetResolved();
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        parent::tearDown();
    }

    public function test_landing_page_renders_empty_state(): void
    {
        Livewire::test(OrganizationCommunications::class)
            ->assertSee('Prepare your first organization communication')
            ->assertSee('Create communication');
    }

    public function test_landing_page_renders_populated_list(): void
    {
        $this->manager->create($this->scope, OrganizationCommunicationKind::CAMPAIGN, ['title' => 'Easter Together']);

        Livewire::test(OrganizationCommunications::class)
            ->assertSee('Easter Together')
            ->assertSee('Shared campaign')
            ->assertSee('Draft');
    }

    public function test_detail_page_renders_immediately_after_creation_with_its_own_audit_event(): void
    {
        // A brand-new communication already has one audit event
        // (COMMUNICATION_CREATED) — this is the exact condition that
        // crashed on manual verification.
        $communication = $this->manager->create($this->scope, OrganizationCommunicationKind::COMMUNICATION, ['title' => 'Founding notice']);

        $response = $this->get(OrganizationCommunicationDetail::getUrl(['record' => $communication->id], panel: 'organization'));

        $response->assertOk();
        $response->assertSee('Founding notice');
        $response->assertSee('Resources');
        $response->assertSee('Guidance &amp; dates', false);
        $response->assertSee('Communication created');
    }

    public function test_detail_page_history_tab_renders_through_the_full_lifecycle(): void
    {
        $revision = $this->manager->create($this->scope, OrganizationCommunicationKind::COMMUNICATION, ['title' => 'Lifecycle notice'])
            ->revisions->sole();
        $this->manager->addMaterial($revision, OrganizationCommunicationMaterialType::GENERAL, 'Body copy');
        $workflow = app(OrganizationCommunicationWorkflow::class);
        $approved = $workflow->approve($workflow->submit($revision));

        $response = $this->get(OrganizationCommunicationDetail::getUrl(['record' => $approved->organization_communication_id], panel: 'organization'));

        $response->assertOk();
        $response->assertSee('Approved and ready for sharing');
        $response->assertSee('Submitted for review');
        $response->assertSee('Revision approved');
        $response->assertDontSee('Send now');
        $response->assertDontSee('Distribute');
    }
}
