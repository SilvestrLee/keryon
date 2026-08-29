<?php

namespace Tests\Feature\Organization;

use App\Enums\ChurchOrganizationAssignmentStatus;
use App\Enums\ChurchRole;
use App\Enums\OrganizationAuditEventType;
use App\Enums\OrganizationUnitStatus;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\Organization;
use App\Models\OrganizationAuditEvent;
use App\Models\OrganizationUnit;
use App\Models\OrganizationUnitPath;
use App\Models\OrganizationUnitType;
use App\Models\PrayerRequest;
use App\Models\User;
use App\Organizations\OrganizationHierarchyService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationHierarchyFoundationTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationHierarchyService $hierarchy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hierarchy = app(OrganizationHierarchyService::class);
    }

    public function test_organization_creation_establishes_one_explicit_root_with_a_self_path(): void
    {
        $organization = $this->hierarchy->createOrganization('Example International', 'example-international');

        $this->assertNotNull($organization->root_unit_id);
        $this->assertSame(1, OrganizationUnit::where('organization_id', $organization->id)->whereNull('parent_id')->count());
        $this->assertDatabaseHas('organization_unit_paths', [
            'organization_id' => $organization->id,
            'ancestor_id' => $organization->root_unit_id,
            'descendant_id' => $organization->root_unit_id,
            'depth' => 0,
        ]);
        $this->assertDatabaseHas('organization_audit_events', [
            'organization_id' => $organization->id,
            'event_type' => OrganizationAuditEventType::ORGANIZATION_CREATED->value,
        ]);
    }

    public function test_closure_paths_prove_positive_and_negative_church_ancestry(): void
    {
        [$organization, $type] = $this->organizationWithType();
        $root = $organization->rootUnit;
        $nigeria = $this->unit($organization, $type, $root, 'Nigeria', 'ng');
        $southSouth = $this->unit($organization, $type, $nigeria, 'South-South', 'ss');
        $rivers = $this->unit($organization, $type, $southSouth, 'Rivers', 'rivers');
        $unitedKingdom = $this->unit($organization, $type, $root, 'United Kingdom', 'uk');
        [$church, $primary] = $this->churchWithPrimary();

        $assignment = $this->hierarchy->attachChurch($organization, $rivers, $church, 9001);
        $this->hierarchy->acceptAttachment($assignment, $primary);

        foreach ([$root, $nigeria, $southSouth, $rivers] as $ancestor) {
            $this->assertTrue($this->ancestorContainsChurch($ancestor, $church));
        }
        $this->assertFalse($this->ancestorContainsChurch($unitedKingdom, $church));
    }

    public function test_moving_a_subtree_rebuilds_external_paths_and_preserves_assignment_and_church_identity(): void
    {
        [$organization, $type] = $this->organizationWithType();
        $root = $organization->rootUnit;
        $nigeria = $this->unit($organization, $type, $root, 'Nigeria', 'ng');
        $southSouth = $this->unit($organization, $type, $nigeria, 'South-South', 'ss');
        $northCentral = $this->unit($organization, $type, $nigeria, 'North-Central', 'nc');
        $rivers = $this->unit($organization, $type, $southSouth, 'Rivers', 'rivers');
        $district = $this->unit($organization, $type, $rivers, 'Port Harcourt', 'ph');
        [$church, $primary] = $this->churchWithPrimary();
        $assignment = $this->hierarchy->attachChurch($organization, $rivers, $church);
        $this->hierarchy->acceptAttachment($assignment, $primary);
        $churchId = $church->id;
        $membershipCount = $church->memberships()->count();

        $this->hierarchy->moveUnit($rivers, $northCentral, $primary->id);

        $this->assertDatabaseMissing('organization_unit_paths', ['ancestor_id' => $southSouth->id, 'descendant_id' => $rivers->id]);
        $this->assertDatabaseMissing('organization_unit_paths', ['ancestor_id' => $southSouth->id, 'descendant_id' => $district->id]);
        $this->assertDatabaseHas('organization_unit_paths', ['ancestor_id' => $northCentral->id, 'descendant_id' => $rivers->id, 'depth' => 1]);
        $this->assertDatabaseHas('organization_unit_paths', ['ancestor_id' => $northCentral->id, 'descendant_id' => $district->id, 'depth' => 2]);
        $this->assertDatabaseHas('organization_unit_paths', ['ancestor_id' => $rivers->id, 'descendant_id' => $rivers->id, 'depth' => 0]);
        $this->assertSame($assignment->id, $church->fresh()->current_organization_assignment_id);
        $this->assertSame($churchId, $church->fresh()->id);
        $this->assertSame($membershipCount, $church->memberships()->count());
        $this->assertTrue($this->ancestorContainsChurch($northCentral, $church));
    }

    public function test_invalid_unit_moves_fail_atomically_without_changing_paths(): void
    {
        [$organization, $type] = $this->organizationWithType();
        $root = $organization->rootUnit;
        $parent = $this->unit($organization, $type, $root, 'Parent', 'parent');
        $child = $this->unit($organization, $type, $parent, 'Child', 'child');
        [$otherOrganization, $otherType] = $this->organizationWithType('Other Organization', 'other-org');
        $foreign = $this->unit($otherOrganization, $otherType, $otherOrganization->rootUnit, 'Foreign', 'foreign');
        $before = OrganizationUnitPath::orderBy('organization_id')->orderBy('ancestor_id')->orderBy('descendant_id')->get()->toArray();

        foreach ([[$parent, $parent], [$parent, $child], [$root, $child], [$parent, $foreign]] as [$moving, $destination]) {
            try {
                $this->hierarchy->moveUnit($moving, $destination);
                $this->fail('Invalid hierarchy move was not rejected.');
            } catch (DomainException) {
                $this->assertSame($before, OrganizationUnitPath::orderBy('organization_id')->orderBy('ancestor_id')->orderBy('descendant_id')->get()->toArray());
            }
        }
    }

    public function test_attachment_is_pending_and_idempotent_until_active_primary_accepts(): void
    {
        [$organization, $type] = $this->organizationWithType();
        $unit = $this->unit($organization, $type, $organization->rootUnit, 'Nigeria', 'ng');
        [$church, $primary] = $this->churchWithPrimary();
        $administrator = User::factory()->forChurch($church, [ChurchRole::ADMINISTRATOR])->create();

        $assignment = $this->hierarchy->attachChurch($organization, $unit, $church, 812);
        $duplicate = $this->hierarchy->attachChurch($organization, $unit, $church, 812);

        $this->assertSame($assignment->id, $duplicate->id);
        $this->assertSame(ChurchOrganizationAssignmentStatus::PENDING, $assignment->status);
        $this->assertNull($church->fresh()->current_organization_assignment_id);
        try {
            $this->hierarchy->acceptAttachment($assignment, $administrator);
            $this->fail('A non-Primary Administrator accepted an Organization attachment.');
        } catch (DomainException) {
            $this->assertSame(ChurchOrganizationAssignmentStatus::PENDING, $assignment->fresh()->status);
            $this->assertNull($church->fresh()->current_organization_assignment_id);
        }

        $accepted = $this->hierarchy->acceptAttachment($assignment, $primary);
        $this->assertSame(ChurchOrganizationAssignmentStatus::ACTIVE, $accepted->status);
    }

    public function test_active_primary_acceptance_sets_the_authoritative_current_pointer_and_blocks_a_second_assignment(): void
    {
        [$organization, $type] = $this->organizationWithType();
        $unit = $this->unit($organization, $type, $organization->rootUnit, 'Nigeria', 'ng');
        [$otherOrganization, $otherType] = $this->organizationWithType('Other Organization', 'other-org');
        $otherUnit = $this->unit($otherOrganization, $otherType, $otherOrganization->rootUnit, 'Other Root Child', 'other');
        [$church, $primary] = $this->churchWithPrimary();

        $accepted = $this->hierarchy->acceptAttachment($this->hierarchy->attachChurch($organization, $unit, $church), $primary);

        $this->assertSame(ChurchOrganizationAssignmentStatus::ACTIVE, $accepted->status);
        $this->assertSame($primary->id, $accepted->accepted_by);
        $this->assertSame($accepted->id, $church->fresh()->current_organization_assignment_id);
        $this->expectException(DomainException::class);
        $this->hierarchy->attachChurch($otherOrganization, $otherUnit, $church);
    }

    public function test_primary_can_reject_pending_attachment_without_erasing_history(): void
    {
        [$organization, $type] = $this->organizationWithType();
        $unit = $this->unit($organization, $type, $organization->rootUnit, 'Nigeria', 'ng');
        [$church, $primary] = $this->churchWithPrimary();
        $assignment = $this->hierarchy->attachChurch($organization, $unit, $church);

        $rejected = $this->hierarchy->rejectAttachment($assignment, $primary, 'Governance relationship declined');

        $this->assertSame(ChurchOrganizationAssignmentStatus::REJECTED, $rejected->status);
        $this->assertNotNull($rejected->ended_at);
        $this->assertNull($church->fresh()->current_organization_assignment_id);
        $this->assertDatabaseHas('church_organization_assignments', ['id' => $assignment->id, 'status' => 'rejected']);
    }

    public function test_move_and_detach_preserve_church_membership_care_data_and_assignment_history(): void
    {
        [$organization, $type] = $this->organizationWithType();
        $firstUnit = $this->unit($organization, $type, $organization->rootUnit, 'Rivers', 'rivers');
        $secondUnit = $this->unit($organization, $type, $organization->rootUnit, 'Lagos', 'lagos');
        [$church, $primary] = $this->churchWithPrimary();
        User::factory()->forChurch($church, [ChurchRole::CARE])->create();
        $prayerRequest = new PrayerRequest(['request' => 'Private care evidence']);
        $prayerRequest->forceFill(['church_id' => $church->id])->save();
        $initial = $this->hierarchy->acceptAttachment($this->hierarchy->attachChurch($organization, $firstUnit, $church), $primary);

        $moved = $this->hierarchy->moveChurch($church, $secondUnit, $primary->id);

        $this->assertSame(ChurchOrganizationAssignmentStatus::ENDED, $initial->fresh()->status);
        $this->assertSame($moved->id, $church->fresh()->current_organization_assignment_id);
        $this->assertSame($prayerRequest->id, $prayerRequest->fresh()->id);
        $this->assertSame(2, $church->memberships()->count());

        $detached = $this->hierarchy->detachChurch($church, $primary->id, 'Returning to independent governance');

        $this->assertSame(ChurchOrganizationAssignmentStatus::ENDED, $detached->status);
        $this->assertNull($church->fresh()->current_organization_assignment_id);
        $this->assertSame(2, $church->organizationAssignments()->count());
        $this->assertSame(2, $church->memberships()->count());
        $this->assertNotNull($prayerRequest->fresh());
    }

    public function test_direct_cross_organization_church_move_is_denied(): void
    {
        [$organization, $type] = $this->organizationWithType();
        $unit = $this->unit($organization, $type, $organization->rootUnit, 'Nigeria', 'ng');
        [$otherOrganization, $otherType] = $this->organizationWithType('Other Organization', 'other-org');
        $foreignUnit = $this->unit($otherOrganization, $otherType, $otherOrganization->rootUnit, 'Foreign', 'foreign');
        [$church, $primary] = $this->churchWithPrimary();
        $active = $this->hierarchy->acceptAttachment($this->hierarchy->attachChurch($organization, $unit, $church), $primary);

        try {
            $this->hierarchy->moveChurch($church, $foreignUnit, $primary->id);
            $this->fail('Cross-Organization movement was not denied.');
        } catch (DomainException) {
            $this->assertSame($active->id, $church->fresh()->current_organization_assignment_id);
            $this->assertSame(ChurchOrganizationAssignmentStatus::ACTIVE, $active->fresh()->status);
        }
    }

    public function test_archival_refuses_root_active_children_and_active_churches_but_preserves_closure_history(): void
    {
        [$organization, $type] = $this->organizationWithType();
        $parent = $this->unit($organization, $type, $organization->rootUnit, 'Parent', 'parent');
        $leaf = $this->unit($organization, $type, $parent, 'Leaf', 'leaf');

        foreach ([$organization->rootUnit, $parent] as $blocked) {
            try {
                $this->hierarchy->archiveUnit($blocked);
                $this->fail('Unsafe archival was not rejected.');
            } catch (DomainException) {
                $this->assertSame(OrganizationUnitStatus::ACTIVE, $blocked->fresh()->status);
            }
        }

        [$church, $primary] = $this->churchWithPrimary();
        $this->hierarchy->acceptAttachment($this->hierarchy->attachChurch($organization, $leaf, $church), $primary);
        $this->expectException(DomainException::class);
        $this->hierarchy->archiveUnit($leaf);
    }

    public function test_audit_evidence_is_bounded_and_covers_all_supported_mutations(): void
    {
        [$organization, $type] = $this->organizationWithType();
        $first = $this->unit($organization, $type, $organization->rootUnit, 'First', 'first');
        $second = $this->unit($organization, $type, $organization->rootUnit, 'Second', 'second');
        [$church, $primary] = $this->churchWithPrimary();
        $request = $this->hierarchy->attachChurch($organization, $first, $church, $primary->id);
        $this->hierarchy->acceptAttachment($request, $primary);
        $this->hierarchy->moveUnit($first, $second, $primary->id);
        $this->hierarchy->moveChurch($church, $second, $primary->id);
        $this->hierarchy->detachChurch($church, $primary->id);
        $this->hierarchy->archiveUnit($first, $primary->id);

        $events = OrganizationAuditEvent::where('organization_id', $organization->id)->pluck('event_type')->map->value->all();
        foreach ([
            OrganizationAuditEventType::ORGANIZATION_CREATED->value,
            OrganizationAuditEventType::UNIT_CREATED->value,
            OrganizationAuditEventType::UNIT_MOVED->value,
            OrganizationAuditEventType::ATTACHMENT_REQUESTED->value,
            OrganizationAuditEventType::ATTACHMENT_ACCEPTED->value,
            OrganizationAuditEventType::CHURCH_MOVED->value,
            OrganizationAuditEventType::CHURCH_DETACHED->value,
            OrganizationAuditEventType::UNIT_ARCHIVED->value,
        ] as $event) {
            $this->assertContains($event, $events);
        }
        OrganizationAuditEvent::where('organization_id', $organization->id)->get()->each(function (OrganizationAuditEvent $audit): void {
            $this->assertArrayNotHasKey('care', $audit->previous_state ?? []);
            $this->assertArrayNotHasKey('care', $audit->new_state ?? []);
        });
    }

    /** @return array{Organization, OrganizationUnitType} */
    private function organizationWithType(string $name = 'Example International', string $slug = 'example-international'): array
    {
        $organization = $this->hierarchy->createOrganization($name, $slug);
        $type = OrganizationUnitType::create([
            'organization_id' => $organization->id,
            'code' => 'region',
            'label' => 'Region',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        return [$organization, $type];
    }

    private function unit(Organization $organization, OrganizationUnitType $type, OrganizationUnit $parent, string $name, string $code): OrganizationUnit
    {
        return $this->hierarchy->createUnit($organization, $type, $parent, compact('name', 'code'));
    }

    /** @return array{Church, User} */
    private function churchWithPrimary(): array
    {
        $church = Church::factory()->create();
        $primary = User::factory()->create();
        ChurchMembership::createPrimary($church, $primary, [ChurchRole::ADMINISTRATOR]);

        return [$church, $primary];
    }

    private function ancestorContainsChurch(OrganizationUnit $ancestor, Church $church): bool
    {
        $assignment = $church->fresh()->currentOrganizationAssignment;

        return $assignment !== null && OrganizationUnitPath::query()
            ->where('organization_id', $ancestor->organization_id)
            ->where('ancestor_id', $ancestor->id)
            ->where('descendant_id', $assignment->organization_unit_id)
            ->exists();
    }
}
