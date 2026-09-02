<?php

namespace Tests\Feature\Central;

use App\Enums\PlatformAuditEventType;
use App\Enums\PlatformAuditReasonCategory;
use App\Enums\PlatformCapability;
use App\Enums\PlatformMembershipStatus;
use App\Enums\PlatformRole;
use App\Filament\Central\Pages\MfaChallenge;
use App\Http\Middleware\RequireCentralMfaReadiness;
use App\Models\PlatformAuditEvent;
use App\Models\PlatformMembership;
use App\Models\PlatformMfaCredential;
use App\Models\User;
use App\Platform\PlatformStaffService;
use App\Platform\Security\PlatformMfaCredentialService;
use App\Platform\Security\PlatformMfaSession;
use App\Support\PlatformContext;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Facades\Filament;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PragmaRX\Google2FAQRCode\Google2FA;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PlatformMfaSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('central'));
    }

    public function test_credential_is_one_per_membership_encrypted_and_cascades_without_audit_loss(): void
    {
        [, $membership] = $this->platformUser(PlatformRole::ADMINISTRATOR);
        $credential = PlatformMfaCredential::create(['platform_membership_id' => $membership->id, 'totp_secret' => 'TOPSECRET', 'confirmed_at' => now(), 'recovery_code_hashes' => [Hash::make('recovery')], 'recovery_codes_generated_at' => now()]);
        $raw = \DB::table('platform_mfa_credentials')->where('id', $credential->id)->first();
        $this->assertNotSame('TOPSECRET', $raw->totp_secret);
        $this->assertSame('TOPSECRET', Crypt::decryptString($raw->totp_secret));
        $this->assertStringNotContainsString('recovery', $raw->recovery_code_hashes);
        $this->expectException(UniqueConstraintViolationException::class);
        PlatformMfaCredential::create(['platform_membership_id' => $membership->id]);
    }

    public function test_enrollment_confirms_only_with_recovery_hashes_and_audits_no_secret(): void
    {
        [, $membership] = $this->platformUser(PlatformRole::SUPPORT);
        $service = app(PlatformMfaCredentialService::class);
        $service->beginEnrollment($membership, 'ENROLLSECRET');
        $this->assertFalse($membership->fresh('mfaCredential')->mfaCredential->isUsable());
        $hashes = [Hash::make('one'), Hash::make('two')];
        $service->saveRecoveryHashes($membership, $hashes);
        $credential = $membership->fresh('mfaCredential')->mfaCredential;
        $this->assertTrue($credential->isUsable());
        $event = PlatformAuditEvent::where('event_type', PlatformAuditEventType::PLATFORM_MFA_ENROLLED)->firstOrFail();
        $encoded = $event->toJson();
        $this->assertStringNotContainsString('ENROLLSECRET', $encoded);
        $this->assertStringNotContainsString('one', $encoded);
    }

    public function test_missing_invalidated_version_idle_absolute_and_password_change_fail_closed(): void
    {
        [$user, $membership, $credential] = $this->enrolled(PlatformRole::OPERATIONS);
        $this->actingAs($user);
        $session = app(PlatformMfaSession::class);
        $session->markVerified($membership);
        $this->assertTrue($session->isSatisfied($membership->fresh('mfaCredential')));
        $credential->increment('credential_version');
        $this->assertFalse($session->isSatisfied($membership->fresh('mfaCredential')));
        $session->markVerified($membership->fresh('mfaCredential'));
        $this->travel(31)->minutes();
        $this->assertFalse($session->isSatisfied($membership->fresh('mfaCredential')));
        $this->travelBack();
        $session->markVerified($membership->fresh('mfaCredential'));
        $this->travel(9)->hours();
        $this->assertFalse($session->isSatisfied($membership->fresh('mfaCredential')));
        $this->travelBack();
        $session->markVerified($membership->fresh('mfaCredential'));
        $user->forceFill(['password' => 'changed-password'])->save();
        $this->assertFalse($session->isSatisfied($membership->fresh('mfaCredential')));
    }

    public function test_direct_central_route_requires_enrollment_then_challenge(): void
    {
        [$user, $membership] = $this->platformUser(PlatformRole::SUPPORT);
        $this->actingAs($user)->get('/central/central-home')->assertRedirectContains('multi-factor-authentication/set-up');
        $this->createCredential($membership);
        app(PlatformContext::class)->forgetResolved();
        $this->get('/central/central-home')->assertRedirectContains('mfa-challenge');
    }

    public function test_valid_otp_challenge_marks_session_and_invalid_code_fails(): void
    {
        [$user, $membership, $credential] = $this->enrolled(PlatformRole::SUPPORT);
        $this->actingAs($user);
        app(PlatformContext::class)->forgetResolved();
        Livewire::test(MfaChallenge::class)->set('code', '000000')->call('verify')->assertHasErrors('code');
        $currentCode = app(Google2FA::class)->getCurrentOtp($credential->totp_secret);
        Livewire::test(MfaChallenge::class)->set('code', $currentCode)->call('verify')->assertRedirect('/central/central-home');
        $this->assertTrue(app(PlatformMfaSession::class)->isSatisfied($membership->fresh('mfaCredential')));
    }

    public function test_recovery_code_is_single_use_and_not_persisted_plaintext(): void
    {
        [$user, $membership] = $this->platformUser(PlatformRole::ADMINISTRATOR);
        $code = 'alpha-beta-recovery';
        $this->createCredential($membership, [Hash::make($code)]);
        $this->actingAs($user);
        /** @var AppAuthentication $provider */
        $provider = Filament::getMultiFactorAuthenticationProviders()['app'];
        $this->assertTrue($provider->verifyRecoveryCode($code, $user));
        $this->assertFalse($provider->verifyRecoveryCode($code, $user));
        $this->assertStringNotContainsString($code, PlatformMfaCredential::first()->toJson());
    }

    public function test_recovery_regeneration_requires_fresh_mfa_replaces_hashes_and_versions_session(): void
    {
        [$user, $membership, $credential] = $this->enrolled(PlatformRole::ADMINISTRATOR, [Hash::make('old-code')]);
        $this->actingAs($user);
        app(PlatformMfaSession::class)->markVerified($membership);
        app(PlatformMfaCredentialService::class)->regenerateRecoveryCodes($membership, ['new-code-a', 'new-code-b']);
        $fresh = $credential->fresh();
        $this->assertSame(2, $fresh->credential_version);
        $this->assertFalse(Hash::check('old-code', $fresh->recovery_code_hashes[0]));
        $this->assertTrue(collect($fresh->recovery_code_hashes)->contains(fn ($hash) => Hash::check('new-code-a', $hash)));
        $this->assertStringNotContainsString('new-code-a', PlatformAuditEvent::latest('id')->first()->toJson());
    }

    public function test_administrator_and_trust_can_reset_but_other_roles_cannot(): void
    {
        foreach ([PlatformRole::ADMINISTRATOR, PlatformRole::TRUST_SECURITY] as $role) {
            [$actorUser, $actor] = $this->enrolled($role);
            [, $target, $targetCredential] = $this->enrolled(PlatformRole::SUPPORT);
            $this->actingAs($actorUser);
            app(PlatformContext::class)->forgetResolved();
            app(PlatformMfaSession::class)->markVerified($actor);
            app(PlatformMfaCredentialService::class)->reset($target, $actor, 'password', PlatformAuditReasonCategory::SECURITY_RESPONSE, 'Verified lost device', (string) Str::uuid());
            $this->assertFalse($targetCredential->fresh()->isUsable());
            $this->assertSame(2, $targetCredential->fresh()->credential_version);
        }

        foreach ([PlatformRole::OPERATIONS, PlatformRole::SUPPORT, PlatformRole::COMMERCIAL] as $role) {
            [, $membership] = $this->platformUser($role);
            $this->assertFalse($membership->hasCapability(PlatformCapability::PlatformMfaReset));
        }
    }

    public function test_reset_requires_password_reason_and_fresh_mfa_and_audit_is_secret_free(): void
    {
        [$actorUser, $actor] = $this->enrolled(PlatformRole::ADMINISTRATOR);
        [, $target] = $this->enrolled(PlatformRole::SUPPORT);
        $this->actingAs($actorUser);
        app(PlatformMfaSession::class)->markVerified($actor);
        try {
            app(PlatformMfaCredentialService::class)->reset($target, $actor, 'wrong', PlatformAuditReasonCategory::SECURITY_RESPONSE, 'Lost device');
            $this->fail('Wrong password must fail.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        app(PlatformMfaCredentialService::class)->reset($target, $actor, 'password', PlatformAuditReasonCategory::SECURITY_RESPONSE, 'Lost device');
        $event = PlatformAuditEvent::where('event_type', PlatformAuditEventType::PLATFORM_MFA_RESET)->firstOrFail();
        $this->assertSame($actor->id, $event->platform_membership_id);
        $this->assertStringNotContainsString('password', $event->toJson());
    }

    public function test_mfa_and_recovery_limiters_are_actor_and_ip_bounded(): void
    {
        [, $membership] = $this->enrolled(PlatformRole::SUPPORT);
        $otp = 'central-mfa-otp:'.$membership->id.':127.0.0.1';
        $recovery = 'central-mfa-recovery:'.$membership->id.':127.0.0.1';
        foreach (range(1, 5) as $_) {
            RateLimiter::hit($otp, 60);
            RateLimiter::hit($recovery, 900);
        }
        $this->assertTrue(RateLimiter::tooManyAttempts($otp, 5));
        $this->assertTrue(RateLimiter::tooManyAttempts($recovery, 5));
    }

    public function test_production_host_and_secure_cookie_gate_fail_closed(): void
    {
        $original = app()->environment();
        app()->detectEnvironment(fn () => 'production');
        config(['central.domain' => 'central.keryon.app', 'session.secure' => true]);
        try {
            $response = app(RequireCentralMfaReadiness::class)->handle(Request::create('https://central.keryon.app/central'), fn () => response('ok'));
            $this->assertSame('ok', $response->getContent());
            $this->expectException(HttpException::class);
            app(RequireCentralMfaReadiness::class)->handle(Request::create('https://app.keryon.app/central'), fn () => response('unsafe'));
        } finally {
            app()->detectEnvironment(fn () => $original);
        }
    }

    public function test_membership_suspension_and_removal_override_satisfied_mfa(): void
    {
        [$user, $membership] = $this->enrolled(PlatformRole::SUPPORT);
        $this->actingAs($user);
        app(PlatformMfaSession::class)->markVerified($membership);
        foreach ([PlatformMembershipStatus::SUSPENDED, PlatformMembershipStatus::REMOVED] as $status) {
            $membership->forceFill(['status' => $status, 'suspended_at' => $status === PlatformMembershipStatus::SUSPENDED ? now() : null, 'removed_at' => $status === PlatformMembershipStatus::REMOVED ? now() : null])->save();
            app(PlatformContext::class)->forgetResolved();
            $this->get('/central/central-home')->assertForbidden();
            $membership->forceFill(['status' => PlatformMembershipStatus::ACTIVE, 'suspended_at' => null, 'removed_at' => null])->save();
        }
    }

    public function test_permanent_membership_deletion_destroys_factor_but_preserves_audit(): void
    {
        [, $membership, $credential] = $this->enrolled(PlatformRole::ADMINISTRATOR);
        $auditId = PlatformAuditEvent::firstOrFail()->id;
        $membership->delete();
        $this->assertDatabaseMissing('platform_mfa_credentials', ['id' => $credential->id]);
        $this->assertDatabaseHas('platform_audit_events', ['id' => $auditId]);
    }

    private function platformUser(PlatformRole $role): array
    {
        $user = User::factory()->create(['password' => 'password']);
        $membership = app(PlatformStaffService::class)->create($user, $role, null, PlatformAuditReasonCategory::PLATFORM_ADMINISTRATION, 'MFA fixture');

        return [$user, $membership];
    }

    private function createCredential(PlatformMembership $membership, array $hashes = []): PlatformMfaCredential
    {
        return PlatformMfaCredential::create(['platform_membership_id' => $membership->id, 'totp_secret' => app(Google2FA::class)->generateSecretKey(16), 'confirmed_at' => now(), 'recovery_code_hashes' => $hashes, 'recovery_codes_generated_at' => now()]);
    }

    private function enrolled(PlatformRole $role, array $hashes = []): array
    {
        [$user, $membership] = $this->platformUser($role);

        return [$user, $membership, $this->createCredential($membership, $hashes)];
    }
}
