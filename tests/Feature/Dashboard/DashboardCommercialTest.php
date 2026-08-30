<?php

namespace Tests\Feature\Dashboard;

use App\Dashboard\ChurchDashboardSnapshotBuilder;
use App\Enums\BillingAccountOwnerType;
use App\Enums\ChurchRole;
use App\Enums\SubscriptionStatus;
use App\Models\BillingAccount;
use App\Models\Church;
use App\Models\PricingMarket;
use App\Models\Subscription;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsDashboardContext;
use Tests\TestCase;

class DashboardCommercialTest extends TestCase
{
    use BuildsDashboardContext;
    use RefreshDatabase;

    public function test_primary_and_church_manager_see_actual_active_trial(): void
    {
        [$primaryChurch] = $this->dashboardActor([ChurchRole::ADMINISTRATOR, ChurchRole::COMMUNICATIONS], true);
        $this->trial($primaryChurch, now()->addDays(12));
        $this->assertSame(12, app(ChurchDashboardSnapshotBuilder::class)->build()->trial['days_remaining']);

        [$managerChurch] = $this->dashboardActor([ChurchRole::ADMINISTRATOR]);
        $this->trial($managerChurch, now()->addDays(3));
        $this->assertSame(3, app(ChurchDashboardSnapshotBuilder::class)->build()->trial['days_remaining']);
    }

    public function test_communications_and_care_do_not_see_trial_and_expired_trial_is_not_active(): void
    {
        [$communications] = $this->dashboardActor([ChurchRole::COMMUNICATIONS]);
        $this->trial($communications, now()->addDays(10));
        $this->assertNull(app(ChurchDashboardSnapshotBuilder::class)->build()->trial);

        [$care] = $this->dashboardActor([ChurchRole::CARE]);
        $this->trial($care, now()->addDays(10));
        $this->assertNull(app(ChurchDashboardSnapshotBuilder::class)->build()->trial);

        [$expired] = $this->dashboardActor([ChurchRole::ADMINISTRATOR]);
        $this->trial($expired, now()->subMinute());
        $this->assertNull(app(ChurchDashboardSnapshotBuilder::class)->build()->trial);
    }

    public function test_legacy_church_without_actual_subscription_is_not_labelled_trial(): void
    {
        $this->dashboardActor([ChurchRole::ADMINISTRATOR]);
        $this->assertNull(app(ChurchDashboardSnapshotBuilder::class)->build()->trial);
    }

    private function trial(Church $church, $endsAt): Subscription
    {
        $market = PricingMarket::query()->create(['code' => strtoupper(substr(uniqid(), -2)), 'name' => 'Test Market', 'currency' => 'NGN', 'status' => 'active']);
        $payer = BillingAccount::query()->create(['name' => 'Test payer', 'owner_type' => BillingAccountOwnerType::CHURCH, 'church_id' => $church->id, 'status' => 'active']);
        $subscription = Subscription::query()->create([
            'church_id' => $church->id,
            'billing_account_id' => $payer->id,
            'pricing_market_id' => $market->id,
            'status' => SubscriptionStatus::TRIALING,
            'effective_slot' => 1,
            'started_at' => now(),
            'trial_started_at' => now(),
            'trial_ends_at' => $endsAt,
        ]);
        $church->forceFill(['current_subscription_id' => $subscription->id])->save();
        app(TenantContext::class)->forgetResolved();

        return $subscription;
    }
}
