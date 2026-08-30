<?php

namespace Tests\Feature\Billing;

use App\Billing\BillingAccountService;
use App\Billing\InvoiceDraftingService;
use App\Billing\IssueInvoice;
use App\Billing\Providers\Paystack\PaystackCheckoutService;
use App\Billing\Providers\Paystack\PaystackPaymentGateway;
use App\Billing\Providers\ProviderConfigurationException;
use App\Billing\Providers\ProviderTransportException;
use App\Billing\SubscriptionService;
use App\Billing\ValueObjects\ServicePeriod;
use App\Billing\VoidInvoice;
use App\Commercial\Entitlements\EntitlementResolver;
use App\Commercial\Pricing\PricingCatalogBootstrapper;
use App\Enums\BillingInterval;
use App\Enums\EntitlementKey;
use App\Enums\InvoiceStatus;
use App\Enums\MerchantLegalEntityStatus;
use App\Enums\PaymentSource;
use App\Enums\ProviderWebhookReceiptStatus;
use App\Enums\SubscriptionStatus;
use App\Models\BillingProfile;
use App\Models\CheckoutIntent;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\Invoice;
use App\Models\MerchantLegalEntity;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentProviderReference;
use App\Models\PlanVersion;
use App\Models\ProviderWebhookReceipt;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PaystackSandboxAdapterTest extends TestCase
{
    use RefreshDatabase;

    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();
        $this->secret = 'sk'.'_test_fixture_not_a_credential';
        config()->set('billing.providers.paystack', array_merge(config('billing.providers.paystack'), ['enabled' => true, 'environment' => 'test', 'secret_key' => $this->secret, 'account_key' => 'ng_test', 'enforce_webhook_ips' => false]));
    }

    private function issuedTrialInvoice(): array
    {
        CarbonImmutable::setTestNow('2026-08-30 10:00:00');
        $books = app(PricingCatalogBootstrapper::class)->bootstrap();
        $version = PlanVersion::where('version_code', 'keryon-2026-1')->firstOrFail();
        $price = $books['NG']->prices()->where('billing_interval', 'monthly')->firstOrFail();
        $church = Church::factory()->create();
        $payer = app(BillingAccountService::class)->create('Payer', $church, null, 'test');
        BillingProfile::create(['billing_account_id' => $payer->id, 'billing_name' => 'Synthetic Payer', 'billing_email' => 'payer@example.test', 'country_code' => 'NG', 'preferred_currency' => 'NGN', 'effective_from' => now()]);
        $trial = app(SubscriptionService::class)->startTrial($church, $payer, $books['NG']->market, $version, $price, null, 'test');
        $entity = MerchantLegalEntity::create(['code' => 'test-ng', 'display_name' => 'Test Issuer', 'legal_name' => 'Test Issuer Only', 'country_code' => 'NG', 'status' => MerchantLegalEntityStatus::ACTIVE, 'invoice_series' => 'KY-NG']);
        $period = ServicePeriod::from($trial->trial_ends_at, BillingInterval::MONTHLY);
        $draft = app(InvoiceDraftingService::class)->draft($payer, $entity, 'NGN', [['subscription' => $trial, 'period' => $period]], 'paystack-proof-'.$church->id, null, 'test');
        $invoice = app(IssueInvoice::class)->execute($draft, null, 'test');

        return [$invoice, $trial, $church];
    }

    private function issuedRenewalInvoice(): array
    {
        CarbonImmutable::setTestNow('2026-08-30 10:00:00');
        $books = app(PricingCatalogBootstrapper::class)->bootstrap();
        $version = PlanVersion::where('version_code', 'keryon-2026-1')->firstOrFail();
        $price = $books['NG']->prices()->where('billing_interval', 'monthly')->firstOrFail();
        $church = Church::factory()->create();
        $payer = app(BillingAccountService::class)->create('Payer', $church, null, 'test');
        BillingProfile::create(['billing_account_id' => $payer->id, 'billing_name' => 'Synthetic Payer', 'billing_email' => 'payer@example.test', 'country_code' => 'NG', 'preferred_currency' => 'NGN', 'effective_from' => now()]);
        $subscription = app(SubscriptionService::class)->activate($church, $payer, $books['NG']->market, $version, $price, null, 'test');
        $first = ServicePeriod::from(now(), BillingInterval::MONTHLY);
        $subscription->forceFill(['current_period_start' => $first->start, 'current_period_end' => $first->end])->save();
        $entity = MerchantLegalEntity::create(['code' => 'test-ng', 'display_name' => 'Test Issuer', 'legal_name' => 'Test Issuer Only', 'country_code' => 'NG', 'status' => MerchantLegalEntityStatus::ACTIVE, 'invoice_series' => 'KY-NG']);
        $next = ServicePeriod::from($first->end, BillingInterval::MONTHLY);
        $draft = app(InvoiceDraftingService::class)->draft($payer, $entity, 'NGN', [['subscription' => $subscription, 'period' => $next]], 'paystack-renewal-'.$church->id, null, 'test');

        return [app(IssueInvoice::class)->execute($draft, null, 'test'), $subscription, $next];
    }

    private function initialize(Invoice $invoice): CheckoutIntent
    {
        Http::fake(['https://api.paystack.co/transaction/initialize' => function (Request $request) {
            $payload = $request->data();

            return Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/synthetic', 'access_code' => 'synthetic_access', 'reference' => $payload['reference']]], 200);
        }]);

        return app(PaystackCheckoutService::class)->initialize($invoice, 'https://example.test/payment-return');
    }

    private function webhookBody(CheckoutIntent $intent, int $amount, string $currency = 'NGN', string $event = 'charge.success'): string
    {
        return json_encode(['event' => $event, 'data' => ['id' => 987654321, 'status' => 'success', 'reference' => $intent->local_reference, 'amount' => $amount, 'currency' => $currency, 'domain' => 'test', 'paid_at' => '2026-09-20T10:00:00Z', 'customer' => ['email' => 'payer@example.test']]], JSON_THROW_ON_ERROR);
    }

    private function fakeVerification(CheckoutIntent $intent, int $amount, string $currency = 'NGN', string $domain = 'test', ?string $reference = null): void
    {
        Http::fake(['https://api.paystack.co/transaction/verify/*' => Http::response(['status' => true, 'data' => ['id' => 987654321, 'status' => 'success', 'reference' => $reference ?? $intent->local_reference, 'amount' => $amount, 'currency' => $currency, 'domain' => $domain, 'paid_at' => '2026-09-20T10:00:00Z']], 200)]);
    }

    public function test_initialization_uses_exact_invoice_state_minimal_customer_data_and_bearer_auth(): void
    {
        [$invoice] = $this->issuedTrialInvoice();
        $intent = $this->initialize($invoice);
        $this->assertSame($invoice->amount_due_minor, $intent->amount_minor);
        $this->assertSame('NGN', $intent->currency);
        $this->assertSame('test', $intent->provider_environment);
        $this->assertSame('https://checkout.paystack.com/synthetic', $intent->authorization_url);
        Http::assertSent(function (Request $request) use ($invoice, $intent): bool {
            $data = $request->data();

            return $request->url() === 'https://api.paystack.co/transaction/initialize' && $request->hasHeader('Authorization', 'Bearer '.$this->secret) && $data['amount'] === $invoice->amount_due_minor && $data['currency'] === 'NGN' && $data['reference'] === $intent->local_reference && $data['email'] === 'payer@example.test' && array_keys($data['metadata']) === ['keryon_invoice_uuid'];
        });
        $this->assertDatabaseCount('checkout_intents', 1);
        $this->assertDatabaseCount('payment_provider_references', 1);
    }

    public function test_initialization_is_locally_idempotent(): void
    {
        [$invoice] = $this->issuedTrialInvoice();
        $first = $this->initialize($invoice);
        $second = app(PaystackCheckoutService::class)->initialize($invoice);
        $this->assertSame($first->id, $second->id);
        Http::assertSentCount(1);
    }

    public function test_configuration_and_transport_fail_closed_without_subscription_mutation(): void
    {
        [, $trial] = $this->issuedTrialInvoice();
        config()->set('billing.providers.paystack.enabled', false);
        try {
            app(PaystackPaymentGateway::class)->verify('reference');
            $this->fail('Disabled provider operated.');
        } catch (ProviderConfigurationException) {
            $this->assertSame(SubscriptionStatus::TRIALING, $trial->fresh()->status);
        }
        config()->set('billing.providers.paystack.enabled', true);
        Http::fake(['*' => Http::response([], 401)]);
        try {
            app(PaystackPaymentGateway::class)->verify('reference');
            $this->fail('Authentication failure was not bounded.');
        } catch (ProviderTransportException $e) {
            $this->assertSame('authentication', $e->category);
        }
    }

    public static function transportCases(): array
    {
        return [
            'validation' => [422, 'provider_validation'],
            'unavailable' => [500, 'provider_unavailable'],
            'invalid response' => [200, 'invalid_response'],
        ];
    }

    #[DataProvider('transportCases')]
    public function test_provider_http_failures_are_bounded(int $status, string $category): void
    {
        Http::fake(['*' => Http::response([], $status)]);

        try {
            app(PaystackPaymentGateway::class)->verify('synthetic-reference');
            $this->fail('Provider failure was not mapped.');
        } catch (ProviderTransportException $exception) {
            $this->assertSame($category, $exception->category);
            $this->assertStringNotContainsString($this->secret, $exception->getMessage());
        }
    }

    public function test_provider_connection_failure_is_bounded_as_timeout(): void
    {
        Http::fake(['*' => Http::failedConnection('synthetic connection failure')]);

        try {
            app(PaystackPaymentGateway::class)->verify('synthetic-reference');
            $this->fail('Connection failure was not mapped.');
        } catch (ProviderTransportException $exception) {
            $this->assertSame('timeout', $exception->category);
            $this->assertStringNotContainsString($this->secret, $exception->getMessage());
        }
    }

    public function test_missing_secret_and_live_environment_are_rejected(): void
    {
        config()->set('billing.providers.paystack.secret_key', null);
        $this->expectException(ProviderConfigurationException::class);
        app(PaystackPaymentGateway::class)->verify('synthetic-reference');
    }

    public function test_live_environment_is_rejected(): void
    {
        config()->set('billing.providers.paystack.environment', 'live');
        $this->expectException(ProviderConfigurationException::class);
        app(PaystackPaymentGateway::class)->verify('synthetic-reference');
    }

    public function test_valid_signed_webhook_verifies_reconciles_and_converts_trial_once(): void
    {
        [$invoice, $trial, $church] = $this->issuedTrialInvoice();
        $intent = $this->initialize($invoice);
        $this->fakeVerification($intent, $invoice->amount_due_minor);
        $body = $this->webhookBody($intent, $invoice->amount_due_minor);
        $signature = hash_hmac('sha512', $body, $this->secret);
        $this->call('POST', '/api/billing/webhooks/paystack', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => $signature], $body)->assertOk();
        $this->assertSame(InvoiceStatus::PAID, $invoice->fresh()->status);
        $this->assertSame(SubscriptionStatus::ACTIVE, $trial->fresh()->status);
        $this->assertSame(PaymentSource::PROVIDER, Payment::firstOrFail()->source);
        $this->assertSame(1, PaymentAllocation::count());
        $this->assertSame(2, PaymentProviderReference::count());
        $this->assertTrue(app(EntitlementResolver::class)->allows($church->fresh(), EntitlementKey::WebsiteEnabled));
        $this->assertFalse(app(EntitlementResolver::class)->allows($church->fresh(), EntitlementKey::MarketplacePremiumEnabled));
        $this->call('POST', '/api/billing/webhooks/paystack', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => $signature], $body)->assertOk();
        $this->assertSame(1, Payment::count());
        $this->assertSame(1, PaymentAllocation::count());
        $this->assertSame(1, ProviderWebhookReceipt::count());
    }

    public function test_invalid_wrong_or_mutated_signature_creates_no_receipt(): void
    {
        [$invoice] = $this->issuedTrialInvoice();
        $intent = $this->initialize($invoice);
        $body = $this->webhookBody($intent, $invoice->amount_due_minor);
        $this->call('POST', '/api/billing/webhooks/paystack', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, 'wrong')], $body)->assertUnauthorized();
        $validForOriginal = hash_hmac('sha512', $body, $this->secret);
        $this->call('POST', '/api/billing/webhooks/paystack', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => $validForOriginal], $body.' ')->assertUnauthorized();
        $this->assertSame(0, ProviderWebhookReceipt::count());
        $this->assertSame(0, Payment::count());
    }

    public function test_unknown_signed_event_is_deduplicated_and_financially_inert(): void
    {
        [$invoice, $trial] = $this->issuedTrialInvoice();
        $intent = $this->initialize($invoice);
        $body = $this->webhookBody($intent, $invoice->amount_due_minor, 'NGN', 'customeridentification.success');
        $signature = hash_hmac('sha512', $body, $this->secret);
        $this->call('POST', '/api/billing/webhooks/paystack', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => $signature], $body)->assertOk();
        $this->assertSame(ProviderWebhookReceiptStatus::IGNORED, ProviderWebhookReceipt::firstOrFail()->status);
        $this->assertSame(0, Payment::count());
        $this->assertSame(InvoiceStatus::ISSUED, $invoice->fresh()->status);
        $this->assertSame(SubscriptionStatus::TRIALING, $trial->fresh()->status);
        $this->assertFalse(Schema::hasColumn('provider_webhook_receipts', 'payload'));
    }

    public function test_void_invoice_cannot_be_reconciled(): void
    {
        [$invoice, $trial] = $this->issuedTrialInvoice();
        $intent = $this->initialize($invoice);
        app(VoidInvoice::class)->execute($invoice, null, 'test', 'Synthetic void proof.');
        $this->fakeVerification($intent, $invoice->amount_due_minor);
        $body = $this->webhookBody($intent, $invoice->amount_due_minor);
        $signature = hash_hmac('sha512', $body, $this->secret);

        $this->call('POST', '/api/billing/webhooks/paystack', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => $signature], $body)->assertOk();

        $this->assertSame(0, Payment::count());
        $this->assertSame(InvoiceStatus::VOID, $invoice->fresh()->status);
        $this->assertSame(SubscriptionStatus::TRIALING, $trial->fresh()->status);
    }

    public function test_verified_payment_renews_active_subscription_only_after_invoice_settlement(): void
    {
        [$invoice, $subscription, $next] = $this->issuedRenewalInvoice();
        $intent = $this->initialize($invoice);
        $this->fakeVerification($intent, $invoice->amount_due_minor);
        $body = $this->webhookBody($intent, $invoice->amount_due_minor);
        $signature = hash_hmac('sha512', $body, $this->secret);

        $this->call('POST', '/api/billing/webhooks/paystack', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => $signature], $body)->assertOk();

        $this->assertSame(InvoiceStatus::PAID, $invoice->fresh()->status);
        $this->assertTrue($subscription->fresh()->current_period_end->equalTo($next->end));
    }

    public function test_wrong_provider_account_binding_fails_closed(): void
    {
        [$invoice, $trial] = $this->issuedTrialInvoice();
        $intent = $this->initialize($invoice);
        config()->set('billing.providers.paystack.account_key', 'different_test_account');
        $this->fakeVerification($intent, $invoice->amount_due_minor);
        $body = $this->webhookBody($intent, $invoice->amount_due_minor);
        $signature = hash_hmac('sha512', $body, $this->secret);

        $this->call('POST', '/api/billing/webhooks/paystack', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => $signature], $body)->assertOk();

        $this->assertSame(0, Payment::count());
        $this->assertSame(SubscriptionStatus::TRIALING, $trial->fresh()->status);
    }

    public static function mismatchCases(): array
    {
        return [
            'amount' => [1, 'NGN', 'test', null],
            'currency' => [null, 'USD', 'test', null],
            'environment' => [null, 'NGN', 'live', null],
            'reference' => [null, 'NGN', 'test', 'wrong-keryon-reference'],
        ];
    }

    #[DataProvider('mismatchCases')]
    public function test_verified_amount_currency_reference_and_environment_mismatches_fail_closed(?int $amount, string $currency, string $domain, ?string $reference): void
    {
        [$invoice, $trial] = $this->issuedTrialInvoice();
        $intent = $this->initialize($invoice);
        $this->fakeVerification($intent, $amount ?? $invoice->amount_due_minor, $currency, $domain, $reference);
        $body = $this->webhookBody($intent, $invoice->amount_due_minor);
        $signature = hash_hmac('sha512', $body, $this->secret);
        $this->call('POST', '/api/billing/webhooks/paystack', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => $signature], $body)->assertOk();
        $this->assertSame(0, Payment::count());
        $this->assertSame(InvoiceStatus::ISSUED, $invoice->fresh()->status);
        $this->assertSame(SubscriptionStatus::TRIALING, $trial->fresh()->status);
        $this->assertSame(ProviderWebhookReceiptStatus::FAILED, ProviderWebhookReceipt::firstOrFail()->status);
    }

    public function test_webhook_requires_no_tenant_context_and_creates_no_membership_or_care_data(): void
    {
        [$invoice] = $this->issuedTrialInvoice();
        $intent = $this->initialize($invoice);
        $this->fakeVerification($intent, $invoice->amount_due_minor);
        $body = $this->webhookBody($intent, $invoice->amount_due_minor);
        $signature = hash_hmac('sha512', $body, $this->secret);
        $this->assertGuest();
        $this->call('POST', '/api/billing/webhooks/paystack', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => $signature], $body)->assertOk();
        $this->assertSame(0, ChurchMembership::count());
        $this->assertDatabaseCount('prayer_requests', 0);
    }
}
