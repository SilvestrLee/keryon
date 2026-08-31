<?php

namespace Tests\Feature\InvitationDelivery;

use App\ChurchStaff\InviteChurchStaff;
use App\ChurchStaff\ResendChurchStaffInvitation;
use App\Enums\ChurchRole;
use App\Enums\InvitationDeliveryStatus;
use App\InvitationDelivery\DeliverInvitationAttempt;
use App\InvitationDelivery\RequestChurchStaffInvitationDelivery;
use App\Jobs\DeliverChurchStaffInvitation;
use App\Mail\ChurchStaffInvitationMail;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvitationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    private ChurchMembership $primary;

    protected function setUp(): void
    {
        parent::setUp();
        config(['invitation-delivery.mailer' => 'array', 'staff.terms_version' => 'test-terms-v1', 'staff.privacy_version' => 'test-privacy-v1']);
        $this->church = Church::create(['name' => 'Delivery Proof Church', 'slug' => 'delivery-proof']);
        $user = User::factory()->create(['church_id' => $this->church->id]);
        $this->primary = ChurchMembership::createPrimary($this->church, $user, [ChurchRole::ADMINISTRATOR]);
        $this->actingAs($user);
    }

    public function test_delivery_attempt_encrypts_secret_and_job_carries_only_uuid(): void
    {
        Queue::fake();
        $invitation = app(InviteChurchStaff::class)->execute($this->primary, 'invitee@example.test', [ChurchRole::COMMUNICATIONS], (string) Str::uuid());
        $attempt = app(RequestChurchStaffInvitationDelivery::class)->execute($invitation->invitation, $invitation->rawToken);

        $raw = \DB::table('invitation_delivery_attempts')->where('id', $attempt->id)->value('sensitive_payload');
        $this->assertStringNotContainsString($invitation->rawToken, $raw);
        $this->assertArrayNotHasKey('sensitive_payload', $attempt->toArray());
        Queue::assertPushed(DeliverChurchStaffInvitation::class, fn ($job) => $job->attemptUuid === $attempt->uuid && ! str_contains(serialize($job), $invitation->rawToken));
    }

    public function test_delivery_sends_multipart_care_notice_and_clears_payload(): void
    {
        Queue::fake();
        Mail::fake();
        $invitation = app(InviteChurchStaff::class)->execute($this->primary, 'care@example.test', [ChurchRole::CARE], (string) Str::uuid());
        $attempt = app(RequestChurchStaffInvitationDelivery::class)->execute($invitation->invitation, $invitation->rawToken);
        app(DeliverInvitationAttempt::class)->execute($attempt->uuid, $attempt->subject_type);

        $attempt->refresh();
        $this->assertSame(InvitationDeliveryStatus::PROVIDER_ACCEPTED, $attempt->status);
        $this->assertNull($attempt->sensitive_payload);
        $this->assertNotNull($attempt->sensitive_payload_cleared_at);
        Mail::assertSent(ChurchStaffInvitationMail::class, function ($mail) use ($invitation): bool {
            $html = $mail->render();
            $text = view('mail.text.church-staff-invitation', ['messageData' => $mail->messageData])->render();

            return $mail->hasTo('care@example.test')
                && str_contains($html, 'private prayer requests')
                && str_contains($text, $invitation->rawToken);
        });
    }

    public function test_stale_generation_is_superseded_without_delivery(): void
    {
        Queue::fake();
        Mail::fake();
        $result = app(InviteChurchStaff::class)->execute($this->primary, 'stale@example.test', [ChurchRole::COMMUNICATIONS], (string) Str::uuid());
        $attempt = app(RequestChurchStaffInvitationDelivery::class)->execute($result->invitation, $result->rawToken);
        $result->invitation->forceFill(['token_hash' => hash('sha256', 'replacement')])->save();
        app(DeliverInvitationAttempt::class)->execute($attempt->uuid, $attempt->subject_type);

        $this->assertSame(InvitationDeliveryStatus::SUPERSEDED, $attempt->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_public_landing_exchanges_bearer_for_opaque_continuation_and_sets_headers(): void
    {
        $result = app(InviteChurchStaff::class)->execute($this->primary, 'newperson@example.test', [ChurchRole::COMMUNICATIONS], (string) Str::uuid());
        $response = $this->get(route('invitations.landing', ['type' => 'staff', 'token' => $result->rawToken]));

        $response->assertRedirect();
        $this->assertStringNotContainsString($result->rawToken, $response->headers->get('Location'));
        $response->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_new_user_account_is_invitation_bound_verified_and_grants_no_membership_before_acceptance(): void
    {
        $result = app(InviteChurchStaff::class)->execute($this->primary, 'newperson@example.test', [ChurchRole::COMMUNICATIONS], (string) Str::uuid());
        $landing = $this->get(route('invitations.landing', ['type' => 'staff', 'token' => $result->rawToken]));
        $location = $landing->headers->get('Location');
        $continuation = basename($location);
        $response = $this->post(route('invitations.establish', ['continuation' => $continuation]), [
            'name' => 'Invited Person', 'password' => 'twelve-chars-safe', 'password_confirmation' => 'twelve-chars-safe',
        ]);
        $response->assertRedirect(route('invitations.review', ['continuation' => $continuation]));
        $user = User::where('email', 'newperson@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(\Hash::check('twelve-chars-safe', $user->password));
        $this->assertDatabaseMissing('church_memberships', ['church_id' => $this->church->id, 'user_id' => $user->id]);
    }

    public function test_canonical_acceptance_remains_the_authority_granting_boundary(): void
    {
        $result = app(InviteChurchStaff::class)->execute($this->primary, 'accept@example.test', [ChurchRole::COMMUNICATIONS], (string) Str::uuid());
        $landing = $this->get(route('invitations.landing', ['type' => 'staff', 'token' => $result->rawToken]));
        $continuation = basename($landing->headers->get('Location'));
        $this->post(route('invitations.establish', ['continuation' => $continuation]), [
            'name' => 'Accepted Person', 'password' => 'twelve-chars-safe', 'password_confirmation' => 'twelve-chars-safe',
        ]);
        $user = User::where('email', 'accept@example.test')->firstOrFail();
        $this->assertDatabaseMissing('church_memberships', ['church_id' => $this->church->id, 'user_id' => $user->id]);

        $this->post(route('invitations.accept', ['continuation' => $continuation]), ['legal_acceptance' => '1'])->assertRedirect();
        $this->assertDatabaseHas('church_memberships', ['church_id' => $this->church->id, 'user_id' => $user->id, 'is_primary' => false, 'status' => 'active']);
    }

    public function test_immediate_resend_is_rate_limited_before_token_rotation(): void
    {
        Queue::fake();
        $result = app(InviteChurchStaff::class)->execute($this->primary, 'limited@example.test', [ChurchRole::COMMUNICATIONS], (string) Str::uuid());
        app(RequestChurchStaffInvitationDelivery::class)->execute($result->invitation, $result->rawToken);
        $hash = $result->invitation->fresh()->token_hash;

        try {
            app(ResendChurchStaffInvitation::class)->execute($this->primary, $result->invitation);
            $this->fail('Immediate resend should be rate limited.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('temporarily unavailable', $exception->getMessage());
        }
        $this->assertSame($hash, $result->invitation->fresh()->token_hash);
    }

    public function test_invalid_external_continuation_is_never_accepted_as_redirect(): void
    {
        foreach (['https://evil.example', '//evil.example', 'javascript:alert(1)', '%2F%2Fevil.example'] as $value) {
            $this->get('/invitation/continue/'.urlencode($value))->assertClientError();
        }
    }
}
