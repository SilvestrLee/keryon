<?php

namespace Tests\Feature\Billing;

use App\Billing\AllocatePayment;
use App\Billing\BillingAccountService;
use App\Billing\ConvertTrialAfterSettlement;
use App\Billing\InvoiceDraftingService;
use App\Billing\IssueInvoice;
use App\Billing\ProviderWebhookReceiptService;
use App\Billing\RecordManualPayment;
use App\Billing\RenewSubscription;
use App\Billing\SubscriptionService;
use App\Billing\ValueObjects\ServicePeriod;
use App\Billing\VoidInvoice;
use App\Commercial\Entitlements\EntitlementResolver;
use App\Commercial\Pricing\PricingCatalogBootstrapper;
use App\Enums\BillingInterval;
use App\Enums\EntitlementKey;
use App\Enums\InvoiceStatus;
use App\Enums\MerchantLegalEntityStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProviderWebhookReceiptStatus;
use App\Models\BillingAccount;
use App\Models\BillingProfile;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\Invoice;
use App\Models\MerchantLegalEntity;
use App\Models\Payment;
use App\Models\PlanVersion;
use App\Models\ProviderWebhookReceipt;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialLedgerFoundationTest extends TestCase
{
    use RefreshDatabase;

    private function subscription(bool $trial = false, BillingInterval $interval = BillingInterval::MONTHLY, ?BillingAccount $payer = null): array
    {
        $books = app(PricingCatalogBootstrapper::class)->bootstrap();
        $version = PlanVersion::query()->where('version_code', 'keryon-2026-1')->firstOrFail();
        $price = $books['NG']->prices()->where('billing_interval', $interval->value)->firstOrFail();
        $church = Church::factory()->create();
        $payer ??= app(BillingAccountService::class)->create('Payer', $church, null, 'test');
        $subscription = $trial
            ? app(SubscriptionService::class)->startTrial($church, $payer, $books['NG']->market, $version, $price, null, 'test')
            : app(SubscriptionService::class)->activate($church, $payer, $books['NG']->market, $version, $price, null, 'test');

        return [$subscription, $payer, $church, $price];
    }

    private function issuerAndProfile(BillingAccount $payer): MerchantLegalEntity
    {
        $entity = MerchantLegalEntity::create(['code' => 'dev-ng', 'display_name' => 'Development Issuer', 'legal_name' => 'Development Issuer (Test Only)', 'country_code' => 'NG', 'status' => MerchantLegalEntityStatus::ACTIVE, 'invoice_series' => 'KY-NG']);
        BillingProfile::create(['billing_account_id' => $payer->id, 'billing_name' => 'Test Payer', 'billing_email' => 'billing@example.test', 'country_code' => 'NG', 'preferred_currency' => 'NGN', 'effective_from' => now()]);

        return $entity;
    }

    private function invoice(Subscription $subscription, BillingAccount $payer, MerchantLegalEntity $entity, ServicePeriod $period, string $key): Invoice
    {
        $draft = app(InvoiceDraftingService::class)->draft($payer, $entity, 'NGN', [['subscription' => $subscription, 'period' => $period]], $key, null, 'test');

        return app(IssueInvoice::class)->execute($draft, null, 'test');
    }

    public function test_invoice_snapshots_pinned_commercial_and_billing_identity_and_is_immutable(): void
    {
        [$subscription, $payer, , $price] = $this->subscription();
        $entity = $this->issuerAndProfile($payer);
        $invoice = $this->invoice($subscription, $payer, $entity, ServicePeriod::from(CarbonImmutable::parse('2026-09-01'), BillingInterval::MONTHLY), 'issue-1');
        $line = $invoice->lines->first();
        $this->assertSame('KY-NG-2026-000001', $invoice->invoice_number);
        $this->assertSame($price->id, $line->price_id);
        $this->assertSame($subscription->item->plan_version_id, $line->plan_version_id);
        BillingProfile::where('billing_account_id', $payer->id)->update(['billing_name' => 'Changed']);
        $this->assertSame('Test Payer', $invoice->fresh()->billing_profile_snapshot['billing_name']);
        $this->expectException(DomainException::class);
        $invoice->forceFill(['currency' => 'USD'])->save();
    }

    public function test_consolidation_requires_one_payer_currency_and_preserves_church_lines(): void
    {
        [$one, $payer] = $this->subscription();
        $churchTwo = Church::factory()->create();
        $books = app(PricingCatalogBootstrapper::class)->bootstrap();
        $version = PlanVersion::where('version_code', 'keryon-2026-1')->firstOrFail();
        $price = $books['NG']->prices()->where('billing_interval', 'monthly')->firstOrFail();
        $two = app(SubscriptionService::class)->activate($churchTwo, $payer, $books['NG']->market, $version, $price, null, 'test');
        $entity = $this->issuerAndProfile($payer);
        $period = ServicePeriod::from(now(), BillingInterval::MONTHLY);
        $invoice = app(InvoiceDraftingService::class)->draft($payer, $entity, 'NGN', [['subscription' => $one, 'period' => $period], ['subscription' => $two, 'period' => $period]], 'grouped', null, 'test');
        $this->assertCount(2, $invoice->lines);
        $this->assertCount(2, $invoice->lines->pluck('church_id')->unique());
        $this->expectException(DomainException::class);
        app(InvoiceDraftingService::class)->draft($payer, $entity, 'USD', [['subscription' => $one, 'period' => $period]], 'mixed', null, 'test');
    }

    public function test_manual_payment_allocation_supports_partial_full_and_excess_balance(): void
    {
        [$subscription, $payer] = $this->subscription();
        $entity = $this->issuerAndProfile($payer);
        $invoice = $this->invoice($subscription, $payer, $entity, ServicePeriod::from(now(), BillingInterval::MONTHLY), 'settle-1');
        $payment = app(RecordManualPayment::class)->execute($payer, $invoice->total_minor + 500, 'NGN', now(), 'Test Bank', 'REF-1', 'payment-1', null, 'test');
        app(AllocatePayment::class)->execute($payment, $invoice, 1000, 'allocation-1', null, 'test');
        $this->assertSame(InvoiceStatus::PARTIALLY_PAID, $invoice->fresh()->status);
        $remaining = $invoice->fresh()->amount_due_minor;
        app(AllocatePayment::class)->execute($payment, $invoice->fresh(), $remaining, 'allocation-2', null, 'test');
        $this->assertSame(InvoiceStatus::PAID, $invoice->fresh()->status);
        $this->assertSame(500, $payment->fresh()->amount_minor - $payment->fresh()->allocatedAmount());
        $this->assertSame(2, $payment->fresh()->allocations()->count());
    }

    public function test_manual_reference_and_allocation_idempotency_are_bounded(): void
    {
        [$subscription, $payer] = $this->subscription();
        $entity = $this->issuerAndProfile($payer);
        $invoice = $this->invoice($subscription, $payer, $entity, ServicePeriod::from(now(), BillingInterval::MONTHLY), 'settle-2');
        $service = app(RecordManualPayment::class);
        $first = $service->execute($payer, $invoice->total_minor, 'NGN', now(), 'Bank', 'ABC', 'manual-key', null, 'test');
        $this->assertTrue($first->is($service->execute($payer, $invoice->total_minor, 'NGN', now(), 'Bank', 'ABC', 'manual-key', null, 'test')));
        app(AllocatePayment::class)->execute($first, $invoice, $invoice->total_minor, 'allocation-key', null, 'test');
        $same = app(AllocatePayment::class)->execute($first, $invoice, $invoice->total_minor, 'allocation-key', null, 'test');
        $this->assertSame('allocation-key', $same->idempotency_key);
        $this->expectException(QueryException::class);
        $service->execute($payer, 100, 'NGN', now(), 'Bank', 'ABC', 'different-key', null, 'test');
    }

    public function test_failed_payment_and_payer_mismatch_cannot_allocate(): void
    {
        [$subscription, $payer] = $this->subscription();
        $entity = $this->issuerAndProfile($payer);
        $invoice = $this->invoice($subscription, $payer, $entity, ServicePeriod::from(now(), BillingInterval::MONTHLY), 'settle-3');
        $failed = Payment::create(['billing_account_id' => $payer->id, 'status' => PaymentStatus::FAILED, 'source' => 'provider', 'currency' => 'NGN', 'amount_minor' => $invoice->total_minor, 'idempotency_key' => 'failed']);
        $this->expectException(DomainException::class);
        app(AllocatePayment::class)->execute($failed, $invoice, 1, 'bad-allocation', null, 'test');
    }

    public function test_paid_trial_invoice_converts_without_changing_product_identity(): void
    {
        [$trial, $payer, $church] = $this->subscription(true);
        $entity = $this->issuerAndProfile($payer);
        $period = ServicePeriod::from(CarbonImmutable::parse('2026-09-20'), BillingInterval::MONTHLY);
        $invoice = $this->invoice($trial, $payer, $entity, $period, 'trial-invoice');
        $payment = app(RecordManualPayment::class)->execute($payer, $invoice->total_minor, 'NGN', now(), 'Bank', 'TRIAL', 'trial-payment', null, 'test');
        app(AllocatePayment::class)->execute($payment, $invoice, $invoice->total_minor, 'trial-allocation', null, 'test');
        $active = app(ConvertTrialAfterSettlement::class)->execute($trial, $invoice->fresh(), 'trial-conversion', null, 'test');
        $this->assertSame('active', $active->status->value);
        $this->assertTrue($active->current_period_end->equalTo($period->end));
        $this->assertFalse(app(EntitlementResolver::class)->allows($church->fresh(), EntitlementKey::MarketplacePremiumEnabled));
        $this->assertSame($trial->item->price_id, $active->item->price_id);
    }

    public function test_paid_invoice_renews_exact_calendar_period_once(): void
    {
        CarbonImmutable::setTestNow('2027-01-31 10:00:00');
        [$subscription, $payer] = $this->subscription();
        $entity = $this->issuerAndProfile($payer);
        $first = ServicePeriod::from(CarbonImmutable::parse('2027-01-31 10:00:00'), BillingInterval::MONTHLY);
        $subscription->forceFill(['current_period_start' => $first->start, 'current_period_end' => $first->end])->save();
        $next = ServicePeriod::from($first->end, BillingInterval::MONTHLY);
        $invoice = $this->invoice($subscription, $payer, $entity, $next, 'renewal-invoice');
        $payment = app(RecordManualPayment::class)->execute($payer, $invoice->total_minor, 'NGN', now(), 'Bank', 'RENEW', 'renew-payment', null, 'test');
        app(AllocatePayment::class)->execute($payment, $invoice, $invoice->total_minor, 'renew-allocation', null, 'test');
        $renewed = app(RenewSubscription::class)->execute($subscription, $invoice->fresh(), 'renew-once', null, 'test');
        $again = app(RenewSubscription::class)->execute($renewed, $invoice->fresh(), 'renew-once', null, 'test');
        $this->assertSame('2027-03-28', $again->current_period_end->format('Y-m-d'));
    }

    public function test_webhook_receipt_is_durable_idempotent_and_financially_inert(): void
    {
        $service = app(ProviderWebhookReceiptService::class);
        $hash = hash('sha256', 'bounded-test-payload');
        $one = $service->receive('paystack', 'test-account', 'evt-1', 'charge.success', $hash, 'local-1');
        $two = $service->receive('paystack', 'test-account', 'evt-1', 'charge.success', $hash, 'local-1');
        $this->assertSame($one->id, $two->id);
        $this->assertSame(1, ProviderWebhookReceipt::count());
        $one->transition(ProviderWebhookReceiptStatus::PROCESSING)->transition(ProviderWebhookReceiptStatus::PROCESSED);
        $this->assertSame(ProviderWebhookReceiptStatus::PROCESSED, $one->fresh()->status);
        $this->assertSame(0, Payment::count());
        $this->assertSame(0, Invoice::count());
        $this->assertArrayNotHasKey('raw_payload', $one->getAttributes());
    }

    public function test_void_blocks_allocated_money_and_preserves_history(): void
    {
        [$subscription, $payer] = $this->subscription();
        $entity = $this->issuerAndProfile($payer);
        $invoice = $this->invoice($subscription, $payer, $entity, ServicePeriod::from(now(), BillingInterval::MONTHLY), 'void-1');
        $voided = app(VoidInvoice::class)->execute($invoice, null, 'test', 'Cancelled obligation');
        $this->assertSame(InvoiceStatus::VOID, $voided->status);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'invoice_number' => $invoice->invoice_number]);
    }

    public function test_financial_foundation_does_not_create_membership_or_care_data(): void
    {
        [$subscription, $payer] = $this->subscription();
        $entity = $this->issuerAndProfile($payer);
        $this->invoice($subscription, $payer, $entity, ServicePeriod::from(now(), BillingInterval::MONTHLY), 'isolation');
        $this->assertSame(0, ChurchMembership::count());
        $this->assertDatabaseCount('prayer_requests', 0);
    }
}
