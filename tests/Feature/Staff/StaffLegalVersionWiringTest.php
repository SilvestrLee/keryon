<?php

namespace Tests\Feature\Staff;

use App\ChurchStaff\InviteChurchStaff;
use App\Enums\ChurchRole;
use App\Enums\ChurchStaffInvitationStatus;
use App\Enums\MembershipStatus;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\ChurchStaffInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StaffLegalVersionWiringTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    private ChurchMembership $primary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->church = Church::create(['name' => 'Harbour Church', 'slug' => 'harbour-church']);
        $primaryUser = User::factory()->create(['church_id' => $this->church->id]);
        $this->primary = ChurchMembership::createPrimary($this->church, $primaryUser, [ChurchRole::ADMINISTRATOR, ChurchRole::COMMUNICATIONS]);
        $this->actingAs($primaryUser);
    }

    public function test_direct_acceptance_persists_the_configured_legal_versions(): void
    {
        config(['staff.terms_version' => 'staff-governed-terms-v3', 'staff.privacy_version' => 'staff-governed-privacy-v3']);
        $user = User::factory()->create(['email' => 'direct@example.test']);
        $result = app(InviteChurchStaff::class)->execute($this->primary, $user->email, [ChurchRole::COMMUNICATIONS], (string) Str::uuid());
        $this->actingAs($user);

        $accept = $this->post(route('church-staff-invitations.accept', $result->rawToken), [
            'acceptance_idempotency_key' => (string) Str::uuid(), 'legal_acceptance' => '1',
        ]);
        $accept->assertRedirect();

        $invitation = $result->invitation->fresh();
        $this->assertSame(ChurchStaffInvitationStatus::ACCEPTED, $invitation->status);
        $this->assertSame('staff-governed-terms-v3', $invitation->terms_version);
        $this->assertSame('staff-governed-privacy-v3', $invitation->privacy_version);
    }

    public function test_canonical_continuation_acceptance_uses_server_configuration(): void
    {
        config(['staff.terms_version' => 'staff-governed-terms-v3', 'staff.privacy_version' => 'staff-governed-privacy-v3']);
        $user = User::factory()->create(['email' => 'continuation@example.test', 'password' => 'correct-horse-battery']);
        $result = app(InviteChurchStaff::class)->execute($this->primary, $user->email, [ChurchRole::CARE], (string) Str::uuid());

        $landing = $this->get(route('invitations.landing', ['type' => 'staff', 'token' => $result->rawToken]));
        $continuation = basename($landing->headers->get('Location'));
        $established = $this->post(route('invitations.establish', ['continuation' => $continuation]), ['password' => 'correct-horse-battery']);
        $established->assertRedirect(route('invitations.review', ['continuation' => $continuation]));

        $accept = $this->post(route('invitations.accept', ['continuation' => $continuation]), ['legal_acceptance' => '1']);
        $accept->assertRedirect();

        $invitation = $result->invitation->fresh();
        $this->assertSame(ChurchStaffInvitationStatus::ACCEPTED, $invitation->status);
        $this->assertSame('staff-governed-terms-v3', $invitation->terms_version);
        $this->assertSame('staff-governed-privacy-v3', $invitation->privacy_version);
    }

    public function test_a_forged_client_terms_version_cannot_change_persisted_evidence(): void
    {
        config(['staff.terms_version' => 'staff-governed-terms-v3', 'staff.privacy_version' => 'staff-governed-privacy-v3']);
        $user = User::factory()->create(['email' => 'forgedterms@example.test']);
        $result = app(InviteChurchStaff::class)->execute($this->primary, $user->email, [ChurchRole::COMMUNICATIONS], (string) Str::uuid());
        $this->actingAs($user);

        $accept = $this->post(route('church-staff-invitations.accept', $result->rawToken), [
            'terms_version' => 'attacker-supplied-terms', 'acceptance_idempotency_key' => (string) Str::uuid(), 'legal_acceptance' => '1',
        ]);
        $accept->assertRedirect();

        $this->assertSame('staff-governed-terms-v3', $result->invitation->fresh()->terms_version);
    }

    public function test_a_forged_client_privacy_version_cannot_change_persisted_evidence(): void
    {
        config(['staff.terms_version' => 'staff-governed-terms-v3', 'staff.privacy_version' => 'staff-governed-privacy-v3']);
        $user = User::factory()->create(['email' => 'forgedprivacy@example.test']);
        $result = app(InviteChurchStaff::class)->execute($this->primary, $user->email, [ChurchRole::COMMUNICATIONS], (string) Str::uuid());
        $this->actingAs($user);

        $accept = $this->post(route('church-staff-invitations.accept', $result->rawToken), [
            'privacy_version' => 'attacker-supplied-privacy', 'acceptance_idempotency_key' => (string) Str::uuid(), 'legal_acceptance' => '1',
        ]);
        $accept->assertRedirect();

        $this->assertSame('staff-governed-privacy-v3', $result->invitation->fresh()->privacy_version);
    }

    public function test_missing_staff_terms_config_fails_closed_with_no_mutation(): void
    {
        config(['staff.terms_version' => '', 'staff.privacy_version' => 'staff-governed-privacy-v3']);
        $user = User::factory()->create(['email' => 'blankterms@example.test']);
        $result = app(InviteChurchStaff::class)->execute($this->primary, $user->email, [ChurchRole::COMMUNICATIONS], (string) Str::uuid());
        $this->actingAs($user);

        $this->post(route('church-staff-invitations.accept', $result->rawToken), [
            'acceptance_idempotency_key' => (string) Str::uuid(), 'legal_acceptance' => '1',
        ])->assertStatus(503);

        $this->assertNoAcceptanceMutation($result->invitation, $user);
    }

    public function test_missing_staff_privacy_config_fails_closed_with_no_mutation(): void
    {
        config(['staff.terms_version' => 'staff-governed-terms-v3', 'staff.privacy_version' => '']);
        $user = User::factory()->create(['email' => 'blankprivacy@example.test']);
        $result = app(InviteChurchStaff::class)->execute($this->primary, $user->email, [ChurchRole::COMMUNICATIONS], (string) Str::uuid());
        $this->actingAs($user);

        $this->post(route('church-staff-invitations.accept', $result->rawToken), [
            'acceptance_idempotency_key' => (string) Str::uuid(), 'legal_acceptance' => '1',
        ])->assertStatus(503);

        $this->assertNoAcceptanceMutation($result->invitation, $user);
    }

    public function test_whitespace_only_staff_legal_configuration_fails_closed(): void
    {
        config(['staff.terms_version' => '   ', 'staff.privacy_version' => "\t\n"]);
        $user = User::factory()->create(['email' => 'whitespace@example.test']);
        $result = app(InviteChurchStaff::class)->execute($this->primary, $user->email, [ChurchRole::COMMUNICATIONS], (string) Str::uuid());
        $this->actingAs($user);

        $this->post(route('church-staff-invitations.accept', $result->rawToken), [
            'acceptance_idempotency_key' => (string) Str::uuid(), 'legal_acceptance' => '1',
        ])->assertStatus(503);

        $this->assertNoAcceptanceMutation($result->invitation, $user);
    }

    public function test_canonical_continuation_fails_closed_on_blank_staff_configuration_with_no_mutation(): void
    {
        config(['staff.terms_version' => '', 'staff.privacy_version' => 'staff-governed-privacy-v3']);
        $user = User::factory()->create(['email' => 'continuationblank@example.test', 'password' => 'correct-horse-battery']);
        $result = app(InviteChurchStaff::class)->execute($this->primary, $user->email, [ChurchRole::CARE], (string) Str::uuid());

        $landing = $this->get(route('invitations.landing', ['type' => 'staff', 'token' => $result->rawToken]));
        $continuation = basename($landing->headers->get('Location'));
        $this->post(route('invitations.establish', ['continuation' => $continuation]), ['password' => 'correct-horse-battery'])
            ->assertRedirect(route('invitations.review', ['continuation' => $continuation]));

        $this->post(route('invitations.accept', ['continuation' => $continuation]), ['legal_acceptance' => '1'])
            ->assertStatus(503);

        $this->assertNoAcceptanceMutation($result->invitation, $user);
    }

    public function test_normal_supported_staff_acceptance_still_succeeds_with_expected_roles_and_membership(): void
    {
        config(['staff.terms_version' => 'staff-governed-terms-v3', 'staff.privacy_version' => 'staff-governed-privacy-v3']);
        $user = User::factory()->create(['email' => 'normal@example.test']);
        $result = app(InviteChurchStaff::class)->execute($this->primary, $user->email, [ChurchRole::COMMUNICATIONS, ChurchRole::CARE], (string) Str::uuid());
        $this->actingAs($user);

        $this->post(route('church-staff-invitations.accept', $result->rawToken), [
            'acceptance_idempotency_key' => (string) Str::uuid(), 'legal_acceptance' => '1',
        ])->assertRedirect();

        $membership = ChurchMembership::where('church_id', $this->church->id)->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(MembershipStatus::ACTIVE, $membership->status);
        $this->assertFalse($membership->is_primary);
        $this->assertEqualsCanonicalizing(['communications', 'care'], $membership->roleValues());
    }

    public function test_idempotent_replay_does_not_duplicate_membership_or_acceptance(): void
    {
        config(['staff.terms_version' => 'staff-governed-terms-v3', 'staff.privacy_version' => 'staff-governed-privacy-v3']);
        $user = User::factory()->create(['email' => 'idempotent@example.test']);
        $result = app(InviteChurchStaff::class)->execute($this->primary, $user->email, [ChurchRole::COMMUNICATIONS], (string) Str::uuid());
        $this->actingAs($user);
        $idempotencyKey = (string) Str::uuid();

        $this->post(route('church-staff-invitations.accept', $result->rawToken), [
            'acceptance_idempotency_key' => $idempotencyKey, 'legal_acceptance' => '1',
        ])->assertRedirect();

        $this->post(route('church-staff-invitations.accept', $result->rawToken), [
            'acceptance_idempotency_key' => $idempotencyKey, 'legal_acceptance' => '1',
        ])->assertRedirect();

        $this->assertSame(1, ChurchMembership::where('church_id', $this->church->id)->where('user_id', $user->id)->count());
    }

    private function assertNoAcceptanceMutation(ChurchStaffInvitation $invitation, User $user): void
    {
        $fresh = $invitation->fresh();
        $this->assertSame(ChurchStaffInvitationStatus::PENDING, $fresh->status);
        $this->assertNull($fresh->terms_version);
        $this->assertNull($fresh->privacy_version);
        $this->assertNull($fresh->legal_accepted_at);
        $this->assertNull($fresh->legal_accepted_by_user_id);
        $this->assertDatabaseMissing('church_memberships', ['church_id' => $this->church->id, 'user_id' => $user->id]);
    }
}
