<?php

namespace Tests\Feature\Onboarding;

use App\Commercial\Pricing\PricingCatalogBootstrapper;
use App\Enums\BillingAccountOwnerType;
use App\Enums\BillingInterval;
use App\Enums\ChurchActivationStatus;
use App\Enums\ChurchRole;
use App\Models\Church;
use App\Models\User;
use App\Onboarding\AcceptChurchPrimaryActivation;
use App\Onboarding\ChurchActivationTokenService;
use App\Onboarding\ProvisionChurch;
use App\Onboarding\ProvisionChurchData;
use App\Onboarding\SelectActiveChurch;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChurchProvisioningActivationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PricingCatalogBootstrapper::class)->bootstrap();
    }

    public function test_ng_church_is_provisioned_inactive_with_pinned_commercials_and_no_tenant_or_trial(): void
    {
        $user = User::factory()->create(['email' => 'primary@example.test']);
        $result = $this->provision($user, 'NG', BillingInterval::MONTHLY);

        $this->assertTrue($result->created);
        $this->assertFalse($result->church->is_active);
        $this->assertSame('NG', $result->church->operating_country_code);
        $this->assertSame(ChurchActivationStatus::PENDING, $result->activation->status);
        $this->assertNotNull($result->activation->pricing_market_id);
        $this->assertNotNull($result->activation->plan_version_id);
        $this->assertNotNull($result->activation->price_id);
        $this->assertSame(0, $result->church->memberships()->count());
        $this->assertSame(0, $result->church->subscriptions()->count());
        $this->assertDatabaseCount('billing_accounts', 0);
    }

    public function test_provisioning_is_idempotent_and_slug_rules_are_governed(): void
    {
        $user = User::factory()->create();
        $first = $this->provision($user, 'US', BillingInterval::ANNUAL, 'same-key', 'Grace Church');
        $second = $this->provision($user, 'US', BillingInterval::ANNUAL, 'same-key', 'Different Church');
        $this->assertSame($first->church->id, $second->church->id);
        $this->assertFalse($second->created);

        $third = $this->provision(User::factory()->create(), 'US', BillingInterval::MONTHLY, 'other-key', 'Grace Church');
        $this->assertSame('grace-church-2', $third->church->slug);
        $this->expectException(DomainException::class);
        $this->provision(User::factory()->create(), 'NG', BillingInterval::MONTHLY, 'reserved-key', 'Ignored', 'www');
    }

    public function test_unmapped_country_fails_closed_into_commercial_review_without_token(): void
    {
        $result = $this->provision(User::factory()->create(), 'GB', BillingInterval::MONTHLY);
        $this->assertSame(ChurchActivationStatus::COMMERCIAL_REVIEW, $result->activation->status);
        $this->assertNull($result->activation->pricing_market_id);
        $this->expectException(DomainException::class);
        app(ChurchActivationTokenService::class)->issue($result->activation);
    }

    public function test_token_rotates_expires_and_revokes_without_storing_raw_value(): void
    {
        $activation = $this->provision(User::factory()->create(), 'NG', BillingInterval::MONTHLY)->activation;
        $first = app(ChurchActivationTokenService::class)->issue($activation);
        $this->assertNotSame($first, $activation->fresh()->token_hash);
        $this->assertSame(hash('sha256', $first), $activation->fresh()->token_hash);
        $this->assertEqualsWithDelta(72, now()->diffInHours($activation->fresh()->token_expires_at), 1);
        $second = app(ChurchActivationTokenService::class)->issue($activation);
        $this->assertNotSame(hash('sha256', $first), $activation->fresh()->token_hash);
        $this->assertSame(hash('sha256', $second), $activation->fresh()->token_hash);
        app(ChurchActivationTokenService::class)->revoke($activation);
        $this->assertSame(ChurchActivationStatus::REVOKED, $activation->fresh()->status);
        $this->assertNull($activation->fresh()->token_hash);
    }

    public function test_matching_existing_user_accepts_atomically_with_two_roles_no_care_and_21_day_trial(): void
    {
        $user = User::factory()->create(['email' => 'primary@example.test']);
        $activation = $this->provision($user, 'NG', BillingInterval::MONTHLY)->activation;
        $token = app(ChurchActivationTokenService::class)->issue($activation);
        $result = app(AcceptChurchPrimaryActivation::class)->execute($token, $user, 'test-terms-v1', 'test-privacy-v1', (string) Str::uuid());

        $this->assertTrue($result->church->is_active);
        $this->assertNotNull($result->church->activated_at);
        $this->assertTrue($result->membership->is_primary);
        $this->assertTrue($result->membership->hasRole(ChurchRole::ADMINISTRATOR));
        $this->assertTrue($result->membership->hasRole(ChurchRole::COMMUNICATIONS));
        $this->assertFalse($result->membership->hasRole(ChurchRole::CARE));
        $this->assertEquals(21, $result->subscription->trial_started_at->diffInDays($result->subscription->trial_ends_at));
        $this->assertSame($activation->price_id, $result->subscription->item->price_id);
        $this->assertSame($activation->pricing_market_id, $result->subscription->pricing_market_id);
        $this->assertSame(ChurchActivationStatus::ACCEPTED, $result->activation->status);
        $this->assertDatabaseCount('checkout_intents', 0);
    }

    public function test_wrong_user_and_expired_token_are_denied_without_partial_state(): void
    {
        $invited = User::factory()->create(['email' => 'invited@example.test']);
        $activation = $this->provision($invited, 'NG', BillingInterval::MONTHLY)->activation;
        $token = app(ChurchActivationTokenService::class)->issue($activation);
        try {
            app(AcceptChurchPrimaryActivation::class)->execute($token, User::factory()->create(), 't', 'p', (string) Str::uuid());
            $this->fail('Wrong user accepted activation.');
        } catch (DomainException) {
            $this->assertFalse($activation->church->fresh()->is_active);
            $this->assertDatabaseCount('subscriptions', 0);
        }
        $activation->forceFill(['token_expires_at' => now()->subMinute()])->save();
        $this->expectException(DomainException::class);
        app(AcceptChurchPrimaryActivation::class)->execute($token, $invited, 't', 'p', (string) Str::uuid());
    }

    public function test_existing_multi_church_selection_is_not_overwritten_until_explicit_selection(): void
    {
        $existing = Church::factory()->create();
        $user = User::factory()->forChurch($existing)->create();
        $this->actingAs($user)->withSession(['active_church_id' => $existing->id]);
        $activation = $this->provision($user, 'US', BillingInterval::ANNUAL)->activation;
        $token = app(ChurchActivationTokenService::class)->issue($activation);
        $result = app(AcceptChurchPrimaryActivation::class)->execute($token, $user, 't', 'p', (string) Str::uuid());
        $this->assertSame($existing->id, session('active_church_id'));
        $this->assertSame($existing->id, $user->fresh()->church_id);
        app(SelectActiveChurch::class)->execute($user, $result->church->id);
        $this->assertSame($result->church->id, session('active_church_id'));
    }

    private function provision(User $user, string $country, BillingInterval $interval, ?string $key = null, string $name = 'Launch Church', ?string $slug = null)
    {
        return app(ProvisionChurch::class)->execute(new ProvisionChurchData(
            operatorReference: 'operator:test', origin: 'test', idempotencyKey: $key ?? (string) Str::uuid(),
            churchName: $name, requestedSlug: $slug, operatingCountryCode: $country, timezone: 'Africa/Lagos',
            prospectivePrimaryEmail: $user->email, billingInterval: $interval, payerType: BillingAccountOwnerType::CHURCH,
        ));
    }
}
