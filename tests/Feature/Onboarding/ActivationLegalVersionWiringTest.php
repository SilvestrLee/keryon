<?php

namespace Tests\Feature\Onboarding;

use App\Commercial\Pricing\PricingCatalogBootstrapper;
use App\Enums\BillingAccountOwnerType;
use App\Enums\BillingInterval;
use App\Enums\ChurchActivationStatus;
use App\Models\ChurchActivation;
use App\Models\User;
use App\Onboarding\ChurchActivationTokenService;
use App\Onboarding\ProvisionChurch;
use App\Onboarding\ProvisionChurchData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ActivationLegalVersionWiringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PricingCatalogBootstrapper::class)->bootstrap();
    }

    public function test_canonical_activation_persists_the_configured_legal_versions(): void
    {
        config(['onboarding.legal.terms_version' => 'governed-terms-v9', 'onboarding.legal.privacy_version' => 'governed-privacy-v9']);

        $activation = $this->provisionAndIssue('newprimary@example.test');
        $continuation = $this->landAndEstablishNewUser($activation['token'], 'New Primary');

        $accept = $this->post(route('invitations.accept', ['continuation' => $continuation]), ['legal_acceptance' => '1']);
        $accept->assertRedirect();

        $persisted = $activation['activation']->fresh();
        $this->assertSame(ChurchActivationStatus::ACCEPTED, $persisted->status);
        $this->assertSame('governed-terms-v9', $persisted->terms_version);
        $this->assertSame('governed-privacy-v9', $persisted->privacy_version);
    }

    public function test_blank_terms_version_config_fails_closed(): void
    {
        config(['onboarding.legal.terms_version' => '', 'onboarding.legal.privacy_version' => 'governed-privacy-v9']);

        $activation = $this->provisionAndIssue('blankterms@example.test');
        $continuation = $this->landAndEstablishNewUser($activation['token'], 'Blank Terms');

        $this->post(route('invitations.accept', ['continuation' => $continuation]), ['legal_acceptance' => '1'])
            ->assertStatus(503);

        $fresh = $activation['activation']->fresh();
        $this->assertSame(ChurchActivationStatus::PENDING, $fresh->status);
        $this->assertNull($fresh->terms_version);
        $this->assertNull($fresh->privacy_version);
        $this->assertDatabaseMissing('church_memberships', ['church_id' => $activation['activation']->church_id]);
    }

    public function test_blank_privacy_version_config_fails_closed(): void
    {
        config(['onboarding.legal.terms_version' => 'governed-terms-v9', 'onboarding.legal.privacy_version' => '']);

        $activation = $this->provisionAndIssue('blankprivacy@example.test');
        $continuation = $this->landAndEstablishNewUser($activation['token'], 'Blank Privacy');

        $this->post(route('invitations.accept', ['continuation' => $continuation]), ['legal_acceptance' => '1'])
            ->assertStatus(503);

        $fresh = $activation['activation']->fresh();
        $this->assertSame(ChurchActivationStatus::PENDING, $fresh->status);
        $this->assertNull($fresh->terms_version);
        $this->assertNull($fresh->privacy_version);
        $this->assertDatabaseMissing('church_memberships', ['church_id' => $activation['activation']->church_id]);
    }

    public function test_canonical_activation_never_uses_the_legacy_test_fallback_identifiers(): void
    {
        $activation = $this->provisionAndIssue('nofallback@example.test');
        $continuation = $this->landAndEstablishNewUser($activation['token'], 'No Fallback');

        $this->post(route('invitations.accept', ['continuation' => $continuation]), ['legal_acceptance' => '1'])
            ->assertRedirect();

        $persisted = $activation['activation']->fresh();
        $this->assertNotSame('test-terms-v1', $persisted->terms_version);
        $this->assertNotSame('test-privacy-v1', $persisted->privacy_version);
    }

    public function test_normal_new_user_activation_still_works(): void
    {
        config(['onboarding.legal.terms_version' => 'governed-terms-v9', 'onboarding.legal.privacy_version' => 'governed-privacy-v9']);

        $activation = $this->provisionAndIssue('brandnew@example.test');
        $continuation = $this->landAndEstablishNewUser($activation['token'], 'Brand New');

        $accept = $this->post(route('invitations.accept', ['continuation' => $continuation]), ['legal_acceptance' => '1']);
        $accept->assertRedirect();

        $user = User::where('email', 'brandnew@example.test')->firstOrFail();
        $this->assertDatabaseHas('church_memberships', [
            'church_id' => $activation['activation']->church_id, 'user_id' => $user->id, 'is_primary' => true,
        ]);
    }

    public function test_normal_existing_user_activation_still_works(): void
    {
        config(['onboarding.legal.terms_version' => 'governed-terms-v9', 'onboarding.legal.privacy_version' => 'governed-privacy-v9']);

        $user = User::factory()->create(['email' => 'alreadyhere@example.test', 'password' => 'correct-horse-battery']);
        $activation = $this->provisionAndIssue('alreadyhere@example.test');

        $landing = $this->get(route('invitations.landing', ['type' => 'activation', 'token' => $activation['token']]));
        $continuation = basename($landing->headers->get('Location'));

        $established = $this->post(route('invitations.establish', ['continuation' => $continuation]), [
            'password' => 'correct-horse-battery',
        ]);
        $established->assertRedirect(route('invitations.review', ['continuation' => $continuation]));
        $this->assertAuthenticatedAs($user);

        $accept = $this->post(route('invitations.accept', ['continuation' => $continuation]), ['legal_acceptance' => '1']);
        $accept->assertRedirect();

        $this->assertDatabaseHas('church_memberships', [
            'church_id' => $activation['activation']->church_id, 'user_id' => $user->id, 'is_primary' => true,
        ]);
        $this->assertSame('governed-terms-v9', $activation['activation']->fresh()->terms_version);
        $this->assertSame('governed-privacy-v9', $activation['activation']->fresh()->privacy_version);
    }

    private function landAndEstablishNewUser(string $token, string $name): string
    {
        $landing = $this->get(route('invitations.landing', ['type' => 'activation', 'token' => $token]));
        $continuation = basename($landing->headers->get('Location'));

        $established = $this->post(route('invitations.establish', ['continuation' => $continuation]), [
            'name' => $name, 'password' => 'twelve-chars-safe', 'password_confirmation' => 'twelve-chars-safe',
        ]);
        $established->assertRedirect(route('invitations.review', ['continuation' => $continuation]));

        return $continuation;
    }

    /** @return array{activation: ChurchActivation, token: string} */
    private function provisionAndIssue(string $email): array
    {
        $result = app(ProvisionChurch::class)->execute(new ProvisionChurchData(
            operatorReference: 'operator:test', origin: 'test', idempotencyKey: (string) Str::uuid(),
            churchName: 'Church for '.$email, requestedSlug: null, operatingCountryCode: 'NG', timezone: 'Africa/Lagos',
            prospectivePrimaryEmail: $email, billingInterval: BillingInterval::MONTHLY, payerType: BillingAccountOwnerType::CHURCH,
        ));
        $token = app(ChurchActivationTokenService::class)->issue($result->activation);

        return ['activation' => $result->activation->fresh(), 'token' => $token];
    }
}
