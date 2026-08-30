<?php

namespace Tests\Feature\Onboarding;

use App\Enums\BillingAccountOwnerType;
use App\Enums\ChurchOnboardingStatus;
use App\Enums\ChurchOnboardingStep;
use App\Enums\ChurchRole;
use App\Enums\MembershipStatus;
use App\Filament\Pages\GuidedChurchSetup;
use App\Models\BillingAccount;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\ChurchOnboardingState;
use App\Models\ChurchServiceTime;
use App\Models\ChurchSocialLink;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Onboarding\ChurchOnboardingService;
use App\Support\TenantContext;
use DomainException;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GuidedChurchSetupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_activated_church_starts_one_church_owned_resumable_state(): void
    {
        [$church, $user] = $this->churchUser();
        $service = app(ChurchOnboardingService::class);
        $first = $service->start($church, $user);
        $second = $service->start($church, $user);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(ChurchOnboardingStatus::IN_PROGRESS, $first->status);
        $this->assertSame(ChurchOnboardingStep::IDENTITY, $first->current_step);
        $this->assertNotNull($first->started_at);
        $this->assertSame($user->id, $first->last_actor_user_id);
        $this->assertDatabaseCount('church_onboarding_states', 1);
    }

    public function test_inactive_church_cannot_start_and_existing_church_is_not_forced_into_state(): void
    {
        $church = Church::factory()->inactive()->create();
        $user = User::factory()->create();
        $this->assertDatabaseCount('church_onboarding_states', 0);
        $this->expectException(DomainException::class);
        app(ChurchOnboardingService::class)->start($church, $user);
    }

    public function test_identity_step_updates_only_editable_fields_and_preserves_commercial_identity(): void
    {
        [$church] = $this->churchUser();
        $church->update(['operating_country_code' => 'NG', 'slug' => 'stable-church']);
        $payer = BillingAccount::query()->create(['name' => 'Payer', 'owner_type' => BillingAccountOwnerType::CHURCH, 'church_id' => $church->id, 'status' => 'active']);
        app(ChurchOnboardingService::class)->start($church, auth()->user());

        Livewire::test(GuidedChurchSetup::class)
            ->set('name', 'Updated Church')
            ->set('email', 'hello@example.test')
            ->set('phone', '+234 800 000 0000')
            ->set('address', '1 Church Road')
            ->set('timezone', 'Africa/Lagos')
            ->call('saveIdentity')
            ->assertHasNoErrors();

        $fresh = $church->fresh();
        $this->assertSame('Updated Church', $fresh->name);
        $this->assertSame('NG', $fresh->operating_country_code);
        $this->assertSame('stable-church', $fresh->slug);
        $this->assertSame($payer->id, BillingAccount::query()->first()->id);
        $this->assertSame(ChurchOnboardingStep::BRAND, ChurchOnboardingState::query()->first()->current_step);
    }

    public function test_optional_steps_can_be_skipped_and_completion_needs_no_product_content(): void
    {
        [$church] = $this->churchUser();
        $state = app(ChurchOnboardingService::class)->start($church, auth()->user());
        app(ChurchOnboardingService::class)->advance($state, ChurchOnboardingStep::BRAND, auth()->user());

        Livewire::test(GuidedChurchSetup::class)
            ->call('skip')
            ->call('saveServiceTimes')
            ->call('saveDigitalPresence')
            ->call('finish');

        $state = ChurchOnboardingState::query()->first();
        $this->assertSame(ChurchOnboardingStatus::COMPLETED, $state->status);
        $this->assertNotNull($state->completed_at);
        $this->assertDatabaseCount('church_service_times', 0);
        $this->assertDatabaseCount('church_social_links', 0);
        $this->assertDatabaseCount('website_publications', 0);
        $this->assertDatabaseCount('congregation_members', 0);
        $this->assertDatabaseCount('content_items', 0);
    }

    public function test_multiple_service_times_and_social_links_use_existing_domain_models(): void
    {
        [$church] = $this->churchUser();
        $state = app(ChurchOnboardingService::class)->start($church, auth()->user());
        $state = app(ChurchOnboardingService::class)->advance($state, ChurchOnboardingStep::BRAND, auth()->user());
        app(ChurchOnboardingService::class)->advance($state, ChurchOnboardingStep::SERVICE_TIMES, auth()->user());

        Livewire::test(GuidedChurchSetup::class)
            ->set('serviceTimes', [
                ['label' => 'Sunday Service', 'day_of_week' => 'sunday', 'time' => '9:00 AM'],
                ['label' => 'Midweek Service', 'day_of_week' => 'wednesday', 'time' => '6:00 PM'],
            ])
            ->call('saveServiceTimes')
            ->set('socialLinks', [
                ['platform' => 'instagram', 'url' => 'https://instagram.com/example'],
                ['platform' => 'youtube', 'url' => 'https://youtube.com/@example'],
            ])
            ->call('saveDigitalPresence')
            ->assertHasNoErrors();

        $this->assertSame(2, ChurchServiceTime::query()->count());
        $this->assertSame(2, ChurchSocialLink::query()->count());
        $this->assertSame($church->id, ChurchServiceTime::query()->first()->church_id);
    }

    public function test_dismissal_preserves_access_and_can_reopen_at_same_step(): void
    {
        [$church, $user] = $this->churchUser();
        $state = app(ChurchOnboardingService::class)->start($church, $user);
        $state = app(ChurchOnboardingService::class)->advance($state, ChurchOnboardingStep::BRAND, $user);
        app(ChurchOnboardingService::class)->dismiss($state, $user);

        $this->assertTrue(app(TenantContext::class)->hasContext());
        $resumed = app(ChurchOnboardingService::class)->start($church, $user);
        $this->assertSame(ChurchOnboardingStatus::IN_PROGRESS, $resumed->status);
        $this->assertSame(ChurchOnboardingStep::BRAND, $resumed->current_step);
        $this->assertNotNull($resumed->started_at);
        $this->assertNull($resumed->dismissed_at);
    }

    public function test_multi_church_context_never_reads_or_mutates_sibling_state(): void
    {
        [$churchA, $user] = $this->churchUser();
        $churchB = Church::factory()->create();
        ChurchMembership::factory()->for($churchB)->for($user)->create();
        $stateA = app(ChurchOnboardingService::class)->start($churchA, $user);

        session(['active_church_id' => $churchB->id]);
        app(TenantContext::class)->forgetResolved();
        Livewire::test(GuidedChurchSetup::class)->assertSet('stateId', null);

        $this->assertSame(ChurchOnboardingStatus::IN_PROGRESS, ChurchOnboardingState::withoutGlobalScopes()->find($stateA->id)->status);
        $this->assertSame($churchA->id, ChurchOnboardingState::withoutGlobalScopes()->find($stateA->id)->church_id);
    }

    public function test_care_is_not_required_but_church_membership_is(): void
    {
        [$church, $user, $membership] = $this->churchUser();
        $this->assertFalse($membership->hasRole(ChurchRole::CARE));
        Livewire::test(GuidedChurchSetup::class)->call('start')->assertSet('stateId', fn ($id) => $id !== null);

        $membership->update(['status' => MembershipStatus::SUSPENDED]);
        app(TenantContext::class)->forgetResolved();
        $this->get(GuidedChurchSetup::getUrl())->assertForbidden();
    }

    public function test_organization_membership_alone_grants_no_onboarding_access(): void
    {
        $organization = Organization::query()->create(['name' => 'Organization', 'slug' => 'organization', 'status' => 'active']);
        $user = User::factory()->create();
        OrganizationMembership::query()->create(['organization_id' => $organization->id, 'user_id' => $user->id, 'status' => 'active', 'joined_at' => now()]);
        $this->actingAs($user);

        $this->get(GuidedChurchSetup::getUrl())->assertForbidden();
        $this->assertDatabaseCount('church_onboarding_states', 0);
    }

    /** @return array{Church, User, ChurchMembership} */
    private function churchUser(): array
    {
        $church = Church::factory()->create(['activated_at' => now(), 'operating_country_code' => 'NG']);
        $user = User::factory()->asPrimaryAdministratorOf($church, [ChurchRole::ADMINISTRATOR, ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($user);
        session(['active_church_id' => $church->id]);
        app(TenantContext::class)->forgetResolved();

        return [$church, $user, $user->memberships()->where('church_id', $church->id)->firstOrFail()];
    }
}
