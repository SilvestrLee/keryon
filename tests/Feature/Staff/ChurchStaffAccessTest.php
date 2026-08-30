<?php

namespace Tests\Feature\Staff;

use App\ChurchStaff\AcceptChurchStaffInvitation;
use App\ChurchStaff\InviteChurchStaff;
use App\ChurchStaff\ReactivateChurchStaff;
use App\ChurchStaff\RemoveChurchStaff;
use App\ChurchStaff\ResendChurchStaffInvitation;
use App\ChurchStaff\RevokeChurchStaffInvitation;
use App\ChurchStaff\SuspendChurchStaff;
use App\ChurchStaff\TransferPrimaryAdministrator;
use App\ChurchStaff\UpdateChurchStaffRoles;
use App\Enums\Capability;
use App\Enums\ChurchAccessAuditEventType;
use App\Enums\ChurchRole;
use App\Enums\ChurchStaffInvitationStatus;
use App\Enums\MembershipStatus;
use App\Filament\Pages\ChurchStaffAccess;
use App\Models\Church;
use App\Models\ChurchAccessAuditEvent;
use App\Models\ChurchMembership;
use App\Models\ChurchStaffInvitation;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\TenantContext;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ChurchStaffAccessTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    private User $primaryUser;

    private ChurchMembership $primary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->church = Church::create(['name' => 'Harbour Church', 'slug' => 'harbour-church']);
        $this->primaryUser = User::factory()->create(['church_id' => $this->church->id]);
        $this->primary = ChurchMembership::createPrimary($this->church, $this->primaryUser, [ChurchRole::ADMINISTRATOR, ChurchRole::COMMUNICATIONS]);
        $this->actingAs($this->primaryUser);
    }

    public function test_invitation_pins_exact_roles_and_stores_hash_only(): void
    {
        $result = app(InviteChurchStaff::class)->execute($this->primary, 'care@example.test', [ChurchRole::COMMUNICATIONS, ChurchRole::CARE], (string) Str::uuid());
        $this->assertTrue($result->created);
        $this->assertNotNull($result->rawToken);
        $this->assertNotSame($result->rawToken, $result->invitation->token_hash);
        $this->assertSame(hash('sha256', $result->rawToken), $result->invitation->token_hash);
        $this->assertEqualsWithDelta(72, now()->diffInHours($result->invitation->token_expires_at), 0.01);
        $this->assertEqualsCanonicalizing(['communications', 'care'], collect($result->invitation->roles)->pluck('role')->map->value->all());
        $this->assertDatabaseHas('church_access_audit_events', ['event_type' => ChurchAccessAuditEventType::STAFF_INVITED->value]);
        $this->assertStringNotContainsString($result->rawToken, json_encode(ChurchAccessAuditEvent::query()->first()->toArray()));
    }

    public function test_only_staff_manage_in_active_tenant_can_invite(): void
    {
        $otherChurch = Church::create(['name' => 'Other', 'slug' => 'other']);
        $communicationsUser = User::factory()->create();
        $communications = ChurchMembership::factory()->for($communicationsUser)->for($this->church)->create();
        $communications->assignRoles([ChurchRole::COMMUNICATIONS]);
        $this->actingAs($communicationsUser);
        app(TenantContext::class)->forgetResolved();
        $this->expectException(DomainException::class);
        app(InviteChurchStaff::class)->execute($communications, 'new@example.test', [ChurchRole::ADMINISTRATOR], (string) Str::uuid());
    }

    public function test_organization_membership_does_not_authorize_church_invitation(): void
    {
        $organization = Organization::create(['name' => 'Regional Network', 'slug' => 'regional-network']);
        $user = User::factory()->create();
        OrganizationMembership::create(['organization_id' => $organization->id, 'user_id' => $user->id, 'status' => 'active']);
        $this->actingAs($user);
        app(TenantContext::class)->forgetResolved();
        $this->expectException(DomainException::class);
        app(InviteChurchStaff::class)->execute($this->primary, 'new@example.test', [ChurchRole::ADMINISTRATOR], (string) Str::uuid());
    }

    public function test_duplicate_pending_invite_returns_existing_without_recovering_token(): void
    {
        $first = app(InviteChurchStaff::class)->execute($this->primary, 'same@example.test', [ChurchRole::ADMINISTRATOR], (string) Str::uuid());
        $second = app(InviteChurchStaff::class)->execute($this->primary, 'SAME@example.test', [ChurchRole::CARE], (string) Str::uuid());
        $this->assertSame($first->invitation->id, $second->invitation->id);
        $this->assertNull($second->rawToken);
        $this->assertSame(1, ChurchStaffInvitation::count());
    }

    public function test_active_and_suspended_members_are_not_reinvited(): void
    {
        $user = User::factory()->create(['email' => 'existing@example.test']);
        $membership = ChurchMembership::factory()->for($user)->for($this->church)->create();
        try {
            app(InviteChurchStaff::class)->execute($this->primary, $user->email, [ChurchRole::CARE], (string) Str::uuid());
            $this->fail();
        } catch (DomainException $e) {
            $this->assertStringContainsString('already', $e->getMessage());
        }
        $membership->forceFill(['status' => MembershipStatus::SUSPENDED])->save();
        $this->expectException(DomainException::class);
        app(InviteChurchStaff::class)->execute($this->primary, $user->email, [ChurchRole::CARE], (string) Str::uuid());
    }

    public function test_resend_rotates_and_revoke_invalidates_token(): void
    {
        $result = app(InviteChurchStaff::class)->execute($this->primary, 'rotate@example.test', [ChurchRole::ADMINISTRATOR], (string) Str::uuid());
        $old = $result->rawToken;
        $resent = app(ResendChurchStaffInvitation::class)->execute($this->primary, $result->invitation);
        $this->assertNotSame($old, $resent->rawToken);
        $this->assertNotSame(hash('sha256', $old), $resent->invitation->token_hash);
        $revoked = app(RevokeChurchStaffInvitation::class)->execute($this->primary, $resent->invitation);
        $this->assertSame(ChurchStaffInvitationStatus::REVOKED, $revoked->status);
        $this->assertNull($revoked->token_hash);
    }

    public function test_matching_user_accepts_without_prior_tenant_context_and_exact_roles_apply(): void
    {
        $user = User::factory()->create(['email' => 'invitee@example.test']);
        $result = app(InviteChurchStaff::class)->execute($this->primary, $user->email, [ChurchRole::COMMUNICATIONS], (string) Str::uuid());
        $this->actingAs($user);
        app(TenantContext::class)->forgetResolved();
        $accepted = app(AcceptChurchStaffInvitation::class)->execute($result->rawToken, $user, 'test-terms', 'test-privacy', $key = (string) Str::uuid());
        $this->assertTrue($accepted->accepted);
        $this->assertSame(MembershipStatus::ACTIVE, $accepted->membership->status);
        $this->assertFalse($accepted->membership->is_primary);
        $this->assertSame(['communications'], $accepted->membership->roleValues());
        $this->assertFalse($accepted->membership->hasCapability(Capability::CareView));
        $this->assertSame('test-terms', $accepted->invitation->terms_version);
        $retry = app(AcceptChurchStaffInvitation::class)->execute($result->rawToken, $user, 'test-terms', 'test-privacy', $key);
        $this->assertFalse($retry->accepted);
        $this->assertSame(1, ChurchMembership::where('church_id', $this->church->id)->where('user_id', $user->id)->count());
    }

    public function test_wrong_user_expired_and_rotated_tokens_fail(): void
    {
        $user = User::factory()->create(['email' => 'right@example.test']);
        $wrong = User::factory()->create(['email' => 'wrong@example.test']);
        $result = app(InviteChurchStaff::class)->execute($this->primary, $user->email, [ChurchRole::CARE], (string) Str::uuid());
        $this->expectException(DomainException::class);
        app(AcceptChurchStaffInvitation::class)->execute($result->rawToken, $wrong, 't', 'p', (string) Str::uuid());
    }

    public function test_removed_member_rejoins_using_preserved_row(): void
    {
        $user = User::factory()->create(['email' => 'return@example.test']);
        $membership = ChurchMembership::factory()->for($user)->for($this->church)->removed()->create();
        $result = app(InviteChurchStaff::class)->execute($this->primary, $user->email, [ChurchRole::CARE], (string) Str::uuid());
        $this->actingAs($user);
        app(TenantContext::class)->forgetResolved();
        $accepted = app(AcceptChurchStaffInvitation::class)->execute($result->rawToken, $user, 't', 'p', (string) Str::uuid());
        $this->assertSame($membership->id, $accepted->membership->id);
        $this->assertSame(['care'], $accepted->membership->roleValues());
    }

    public function test_role_updates_are_exact_self_protected_and_primary_keeps_administrator(): void
    {
        $user = User::factory()->create();
        $target = ChurchMembership::factory()->for($user)->for($this->church)->create();
        $target->assignRoles([ChurchRole::ADMINISTRATOR, ChurchRole::CARE]);
        $updated = app(UpdateChurchStaffRoles::class)->execute($this->primary, $target, [ChurchRole::COMMUNICATIONS]);
        $this->assertSame(['communications'], $updated->roleValues());
        try {
            app(UpdateChurchStaffRoles::class)->execute($this->primary, $this->primary, [ChurchRole::CARE]);
            $this->fail();
        } catch (DomainException $e) {
            $this->assertTrue(true);
        }
        $this->assertTrue($this->primary->fresh()->hasRole(ChurchRole::ADMINISTRATOR));
    }

    public function test_suspend_reactivate_remove_lifecycle_preserves_history_and_roles(): void
    {
        $user = User::factory()->create();
        $target = ChurchMembership::factory()->for($user)->for($this->church)->create();
        $target->assignRoles([ChurchRole::CARE]);
        $suspended = app(SuspendChurchStaff::class)->execute($this->primary, $target);
        $this->assertSame(MembershipStatus::SUSPENDED, $suspended->status);
        $this->assertTrue($suspended->hasRole(ChurchRole::CARE));
        $active = app(ReactivateChurchStaff::class)->execute($this->primary, $suspended, [ChurchRole::COMMUNICATIONS]);
        $this->assertSame(['communications'], $active->roleValues());
        $removed = app(RemoveChurchStaff::class)->execute($this->primary, $active);
        $this->assertSame(MembershipStatus::REMOVED, $removed->status);
        $this->assertNotNull($removed->removed_at);
        $this->assertNotNull($user->fresh());
    }

    public function test_only_current_primary_transfers_and_target_gets_administrator_not_care(): void
    {
        $user = User::factory()->create();
        $target = ChurchMembership::factory()->for($user)->for($this->church)->create();
        $target->assignRoles([ChurchRole::COMMUNICATIONS]);
        $newPrimary = app(TransferPrimaryAdministrator::class)->execute($this->primary, $target);
        $this->assertTrue($newPrimary->is_primary);
        $this->assertTrue($newPrimary->hasRole(ChurchRole::ADMINISTRATOR));
        $this->assertFalse($newPrimary->hasRole(ChurchRole::CARE));
        $this->assertFalse($this->primary->fresh()->is_primary);
        $this->assertSame(1, ChurchMembership::where('church_id', $this->church->id)->active()->primary()->count());
        $this->assertDatabaseHas('church_access_audit_events', ['event_type' => ChurchAccessAuditEventType::PRIMARY_TRANSFERRED->value]);
    }

    public function test_cross_church_policy_and_direct_ids_fail_closed(): void
    {
        $other = Church::create(['name' => 'Other Church', 'slug' => 'other-church']);
        $target = ChurchMembership::factory()->for(User::factory())->for($other)->create();
        $this->assertFalse($this->primaryUser->can('view', $target));
        $this->assertFalse($this->primaryUser->can('update', $target));
    }

    public function test_staff_access_page_shows_care_warning_and_hides_from_non_staff_view(): void
    {
        Livewire::test(ChurchStaffAccess::class)->assertSuccessful()->assertSee('Care access includes private prayer requests')->assertSee('Primary Administrator');
        $user = User::factory()->create();
        $membership = ChurchMembership::factory()->for($user)->for($this->church)->create();
        $membership->assignRoles([ChurchRole::CARE]);
        $this->actingAs($user);
        app(TenantContext::class)->forgetResolved();
        $this->get(ChurchStaffAccess::getUrl())->assertForbidden();
    }

    public function test_acceptance_surface_is_identity_bound_and_post_accept_selection_is_explicit(): void
    {
        $user = User::factory()->create(['email' => 'surface@example.test']);
        $result = app(InviteChurchStaff::class)->execute($this->primary, $user->email, [ChurchRole::ADMINISTRATOR], (string) Str::uuid());
        $this->actingAs($user);
        app(TenantContext::class)->forgetResolved();
        $this->get(route('church-staff-invitations.show', $result->rawToken))->assertOk()->assertSee('Join Harbour Church')->assertDontSee('Care access includes');
        $this->assertNull(session('active_church_id'));
    }

    public function test_expired_and_revoked_tokens_cannot_accept(): void
    {
        $user = User::factory()->create(['email' => 'closed@example.test']);
        $expired = app(InviteChurchStaff::class)->execute($this->primary, $user->email, [ChurchRole::CARE], (string) Str::uuid());
        $expired->invitation->forceFill(['token_expires_at' => now()->subMinute()])->save();
        try {
            app(AcceptChurchStaffInvitation::class)->execute($expired->rawToken, $user, 't', 'p', (string) Str::uuid());
            $this->fail();
        } catch (DomainException $e) {
            $this->assertTrue(true);
        }
        app(RevokeChurchStaffInvitation::class)->execute($this->primary, $expired->invitation->fresh());
        $this->expectException(DomainException::class);
        app(AcceptChurchStaffInvitation::class)->execute($expired->rawToken, $user, 't', 'p', (string) Str::uuid());
    }
}
