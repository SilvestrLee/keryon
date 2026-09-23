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

class LegacyActivationLegalVersionWiringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PricingCatalogBootstrapper::class)->bootstrap();
    }

    public function test_direct_acceptance_persists_the_configured_legal_versions(): void
    {
        config(['onboarding.legal.terms_version' => 'legacy-governed-terms-v4', 'onboarding.legal.privacy_version' => 'legacy-governed-privacy-v4']);
        $activation = $this->provisionAndIssue('directprimary@example.test');
        $this->actingAs(User::factory()->create(['email' => 'directprimary@example.test']));

        $accept = $this->post(route('church-activation.accept', $activation['token']), [
            'acceptance_idempotency_key' => (string) Str::uuid(), 'legal_acceptance' => '1',
        ]);
        $accept->assertRedirect();

        $persisted = $activation['activation']->fresh();
        $this->assertSame(ChurchActivationStatus::ACCEPTED, $persisted->status);
        $this->assertSame('legacy-governed-terms-v4', $persisted->terms_version);
        $this->assertSame('legacy-governed-privacy-v4', $persisted->privacy_version);
    }

    public function test_a_forged_client_terms_version_cannot_change_persisted_evidence(): void
    {
        config(['onboarding.legal.terms_version' => 'legacy-governed-terms-v4', 'onboarding.legal.privacy_version' => 'legacy-governed-privacy-v4']);
        $activation = $this->provisionAndIssue('forgedterms@example.test');
        $this->actingAs(User::factory()->create(['email' => 'forgedterms@example.test']));

        $accept = $this->post(route('church-activation.accept', $activation['token']), [
            'terms_version' => 'attacker-supplied-terms', 'acceptance_idempotency_key' => (string) Str::uuid(), 'legal_acceptance' => '1',
        ]);
        $accept->assertRedirect();

        $this->assertSame('legacy-governed-terms-v4', $activation['activation']->fresh()->terms_version);
    }

    public function test_a_forged_client_privacy_version_cannot_change_persisted_evidence(): void
    {
        config(['onboarding.legal.terms_version' => 'legacy-governed-terms-v4', 'onboarding.legal.privacy_version' => 'legacy-governed-privacy-v4']);
        $activation = $this->provisionAndIssue('forgedprivacy@example.test');
        $this->actingAs(User::factory()->create(['email' => 'forgedprivacy@example.test']));

        $accept = $this->post(route('church-activation.accept', $activation['token']), [
            'privacy_version' => 'attacker-supplied-privacy', 'acceptance_idempotency_key' => (string) Str::uuid(), 'legal_acceptance' => '1',
        ]);
        $accept->assertRedirect();

        $this->assertSame('legacy-governed-privacy-v4', $activation['activation']->fresh()->privacy_version);
    }

    public function test_show_fails_closed_when_configuration_is_blank(): void
    {
        config(['onboarding.legal.terms_version' => '', 'onboarding.legal.privacy_version' => 'legacy-governed-privacy-v4']);
        $activation = $this->provisionAndIssue('showblank@example.test');
        $this->actingAs(User::factory()->create(['email' => 'showblank@example.test']));

        $this->get(route('church-activation.show', $activation['token']))->assertStatus(503);
    }

    public function test_missing_terms_config_fails_closed_with_no_mutation(): void
    {
        config(['onboarding.legal.terms_version' => '', 'onboarding.legal.privacy_version' => 'legacy-governed-privacy-v4']);
        $activation = $this->provisionAndIssue('blankterms@example.test');
        $this->actingAs(User::factory()->create(['email' => 'blankterms@example.test']));

        $this->post(route('church-activation.accept', $activation['token']), [
            'acceptance_idempotency_key' => (string) Str::uuid(), 'legal_acceptance' => '1',
        ])->assertStatus(503);

        $this->assertNoAcceptanceMutation($activation['activation']);
    }

    public function test_missing_privacy_config_fails_closed_with_no_mutation(): void
    {
        config(['onboarding.legal.terms_version' => 'legacy-governed-terms-v4', 'onboarding.legal.privacy_version' => '']);
        $activation = $this->provisionAndIssue('blankprivacy@example.test');
        $this->actingAs(User::factory()->create(['email' => 'blankprivacy@example.test']));

        $this->post(route('church-activation.accept', $activation['token']), [
            'acceptance_idempotency_key' => (string) Str::uuid(), 'legal_acceptance' => '1',
        ])->assertStatus(503);

        $this->assertNoAcceptanceMutation($activation['activation']);
    }

    public function test_whitespace_only_configuration_fails_closed(): void
    {
        config(['onboarding.legal.terms_version' => '   ', 'onboarding.legal.privacy_version' => "\t\n"]);
        $activation = $this->provisionAndIssue('whitespace@example.test');
        $this->actingAs(User::factory()->create(['email' => 'whitespace@example.test']));

        $this->post(route('church-activation.accept', $activation['token']), [
            'acceptance_idempotency_key' => (string) Str::uuid(), 'legal_acceptance' => '1',
        ])->assertStatus(503);

        $this->assertNoAcceptanceMutation($activation['activation']);
    }

    public function test_normal_supported_activation_still_succeeds_and_creates_primary_membership(): void
    {
        config(['onboarding.legal.terms_version' => 'legacy-governed-terms-v4', 'onboarding.legal.privacy_version' => 'legacy-governed-privacy-v4']);
        $activation = $this->provisionAndIssue('normalprimary@example.test');
        $user = User::factory()->create(['email' => 'normalprimary@example.test']);
        $this->actingAs($user);

        $this->post(route('church-activation.accept', $activation['token']), [
            'acceptance_idempotency_key' => (string) Str::uuid(), 'legal_acceptance' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('church_memberships', [
            'church_id' => $activation['activation']->church_id, 'user_id' => $user->id, 'is_primary' => true,
        ]);
        $this->assertTrue($activation['activation']->fresh()->church->fresh()->is_active);
    }

    public function test_idempotent_replay_does_not_duplicate_membership_or_acceptance(): void
    {
        config(['onboarding.legal.terms_version' => 'legacy-governed-terms-v4', 'onboarding.legal.privacy_version' => 'legacy-governed-privacy-v4']);
        $activation = $this->provisionAndIssue('idempotentprimary@example.test');
        $user = User::factory()->create(['email' => 'idempotentprimary@example.test']);
        $this->actingAs($user);
        $idempotencyKey = (string) Str::uuid();

        $this->post(route('church-activation.accept', $activation['token']), [
            'acceptance_idempotency_key' => $idempotencyKey, 'legal_acceptance' => '1',
        ])->assertRedirect();

        $this->post(route('church-activation.accept', $activation['token']), [
            'acceptance_idempotency_key' => $idempotencyKey, 'legal_acceptance' => '1',
        ])->assertRedirect();

        $this->assertSame(1, \App\Models\ChurchMembership::where('church_id', $activation['activation']->church_id)->where('user_id', $user->id)->count());
    }

    private function assertNoAcceptanceMutation(ChurchActivation $activation): void
    {
        $fresh = $activation->fresh();
        $this->assertSame(ChurchActivationStatus::PENDING, $fresh->status);
        $this->assertNull($fresh->terms_version);
        $this->assertNull($fresh->privacy_version);
        $this->assertNull($fresh->legal_accepted_at);
        $this->assertNull($fresh->legal_accepted_by_user_id);
        $this->assertDatabaseMissing('church_memberships', ['church_id' => $activation->church_id]);
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
