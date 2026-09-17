<?php

namespace Tests\Feature\Onboarding;

use App\Commercial\Pricing\PricingCatalogBootstrapper;
use App\Enums\BillingAccountOwnerType;
use App\Enums\BillingInterval;
use App\Enums\ChurchActivationStatus;
use App\Models\Church;
use App\Models\ChurchActivation;
use App\Models\ChurchMembership;
use App\Models\User;
use App\Onboarding\AcceptChurchPrimaryActivation;
use App\Onboarding\ChurchActivationTokenService;
use App\Onboarding\ProvisionChurch;
use App\Onboarding\ProvisionChurchData;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProvisionChurchActivationLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PricingCatalogBootstrapper::class)->bootstrap();
    }

    public function test_issue_token_prints_the_canonical_public_invitation_landing_url(): void
    {
        Artisan::call('onboarding:provision-church', [
            'name' => 'Grace Church', 'email' => 'primary@example.test', 'country' => 'NG', 'timezone' => 'Africa/Lagos',
            '--issue-token' => true,
        ]);
        $output = Artisan::output();

        $this->assertStringNotContainsString('church-activation', $output);
        $this->assertMatchesRegularExpression('#/invitation/activation/[A-Za-z0-9]+#', $output);

        $token = $this->extractToken($output);
        $activation = ChurchActivation::query()->firstOrFail();
        $this->assertSame($activation->token_hash, ChurchActivationTokenService::hash($token));
    }

    public function test_issued_token_resolves_the_intended_activation_through_the_public_landing_route(): void
    {
        Artisan::call('onboarding:provision-church', [
            'name' => 'Grace Church', 'email' => 'primary@example.test', 'country' => 'NG', 'timezone' => 'Africa/Lagos',
            '--issue-token' => true,
        ]);
        $token = $this->extractToken(Artisan::output());
        $activation = ChurchActivation::query()->firstOrFail();

        $response = $this->get(route('invitations.landing', ['type' => 'activation', 'token' => $token]));

        $response->assertRedirect();
        $this->assertStringNotContainsString($token, $response->headers->get('Location'));
        $continuation = basename($response->headers->get('Location'));
        $show = $this->get(route('invitations.continue', ['continuation' => $continuation]));
        $show->assertOk()->assertViewHas('subject', fn ($subject) => $subject->is($activation));
    }

    public function test_a_new_identity_can_establish_review_and_accept_through_the_landing_flow(): void
    {
        $activation = $this->provisionAndIssue('newprimary@example.test');
        $token = $activation['token'];

        $landing = $this->get(route('invitations.landing', ['type' => 'activation', 'token' => $token]));
        $continuation = basename($landing->headers->get('Location'));

        $established = $this->post(route('invitations.establish', ['continuation' => $continuation]), [
            'name' => 'New Primary', 'password' => 'twelve-chars-safe', 'password_confirmation' => 'twelve-chars-safe',
        ]);
        $established->assertRedirect(route('invitations.review', ['continuation' => $continuation]));

        $user = User::where('email', 'newprimary@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);

        $this->get(route('invitations.review', ['continuation' => $continuation]))->assertOk();

        $accept = $this->post(route('invitations.accept', ['continuation' => $continuation]), ['legal_acceptance' => '1']);
        $accept->assertRedirect();

        $this->assertTrue($activation['activation']->fresh()->status === ChurchActivationStatus::ACCEPTED);
        $this->assertDatabaseHas('church_memberships', [
            'church_id' => $activation['activation']->church_id, 'user_id' => $user->id, 'is_primary' => true,
        ]);
    }

    public function test_an_existing_matching_user_can_authenticate_through_the_landing_flow(): void
    {
        $user = User::factory()->create(['email' => 'existingprimary@example.test', 'password' => 'correct-horse-battery']);
        $activation = $this->provisionAndIssue('existingprimary@example.test');

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
    }

    public function test_a_non_matching_identity_cannot_review_or_accept(): void
    {
        $activation = $this->provisionAndIssue('intended@example.test');
        $landing = $this->get(route('invitations.landing', ['type' => 'activation', 'token' => $activation['token']]));
        $continuation = basename($landing->headers->get('Location'));

        $other = User::factory()->create(['email' => 'stranger@example.test']);
        $this->actingAs($other);

        $this->get(route('invitations.review', ['continuation' => $continuation]))->assertForbidden();
        $this->post(route('invitations.accept', ['continuation' => $continuation]), ['legal_acceptance' => '1'])->assertForbidden();
        $this->assertDatabaseMissing('church_memberships', ['church_id' => $activation['activation']->church_id, 'user_id' => $other->id]);
    }

    public function test_expired_revoked_or_already_accepted_activation_cannot_enter_the_landing_flow(): void
    {
        $expired = $this->provisionAndIssue('expired@example.test');
        $expired['activation']->forceFill(['token_expires_at' => now()->subMinute()])->save();
        $this->get(route('invitations.landing', ['type' => 'activation', 'token' => $expired['token']]))->assertStatus(410);

        $revoked = $this->provisionAndIssue('revoked@example.test');
        app(ChurchActivationTokenService::class)->revoke($revoked['activation']);
        $this->get(route('invitations.landing', ['type' => 'activation', 'token' => $revoked['token']]))->assertStatus(410);

        $accepted = $this->provisionAndIssue('accepted@example.test');
        $user = User::factory()->create(['email' => 'accepted@example.test']);
        app(AcceptChurchPrimaryActivation::class)->execute($accepted['token'], $user, 'test-terms-v1', 'test-privacy-v1', (string) Str::uuid());
        $this->get(route('invitations.landing', ['type' => 'activation', 'token' => $accepted['token']]))->assertStatus(410);
    }

    public function test_accept_church_primary_activation_remains_the_sole_authority_and_replay_is_idempotent_without_duplication(): void
    {
        $activation = $this->provisionAndIssue('idempotent@example.test');
        $user = User::factory()->create(['email' => 'idempotent@example.test']);
        $idempotencyKey = (string) Str::uuid();

        $first = app(AcceptChurchPrimaryActivation::class)->execute($activation['token'], $user, 'test-terms-v1', 'test-privacy-v1', $idempotencyKey);
        $second = app(AcceptChurchPrimaryActivation::class)->execute($activation['token'], $user, 'test-terms-v1', 'test-privacy-v1', $idempotencyKey);

        $this->assertSame($first->church->id, $second->church->id);
        $this->assertSame($first->membership->id, $second->membership->id);
        $this->assertSame(1, ChurchMembership::where('church_id', $first->church->id)->count());
        $this->assertSame(1, Church::where('id', $first->church->id)->count());

        $this->expectException(DomainException::class);
        app(AcceptChurchPrimaryActivation::class)->execute($activation['token'], $user, 'test-terms-v1', 'test-privacy-v1', (string) Str::uuid());
    }

    public function test_the_raw_token_is_never_persisted_outside_the_existing_hashed_boundary(): void
    {
        Artisan::call('onboarding:provision-church', [
            'name' => 'Grace Church', 'email' => 'primary@example.test', 'country' => 'NG', 'timezone' => 'Africa/Lagos',
            '--issue-token' => true,
        ]);
        $token = $this->extractToken(Artisan::output());

        $this->assertDatabaseMissing('church_activations', ['token_hash' => $token]);
        foreach (Schema::getTableListing() as $table) {
            $columns = Schema::getColumnListing($table);
            foreach ($columns as $column) {
                if (str_contains(strtolower($column), 'token') && ! str_contains(strtolower($column), 'hash')) {
                    $hit = \DB::table($table)->where($column, $token)->exists();
                    $this->assertFalse($hit, "Raw token unexpectedly stored in {$table}.{$column}");
                }
            }
        }
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

    private function extractToken(string $output): string
    {
        preg_match('#/invitation/activation/([A-Za-z0-9]+)#', $output, $matches);
        $this->assertNotEmpty($matches[1] ?? null, 'Expected the canonical invitation landing URL in command output.');

        return $matches[1];
    }
}
