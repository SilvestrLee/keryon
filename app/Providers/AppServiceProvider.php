<?php

namespace App\Providers;

use App\Commercial\Entitlements\ProductEntitlementSource;
use App\Commercial\Entitlements\SubscriptionEntitlementSource;
use App\Design\Rendering\DesignRenderer;
use App\Design\Rendering\PlaywrightDesignRenderer;
use App\Domain\Dns\CloudflareDnsResolver;
use App\Domain\Dns\DnsResolver;
use App\Domain\Dns\FakeDnsResolver;
use App\Domain\Dns\UnavailableDnsResolver;
use App\Domain\Provisioning\CloudflareDomainProvisioner;
use App\Domain\Provisioning\DomainProvisioner;
use App\Domain\Provisioning\FakeDomainProvisioner;
use App\Domain\Provisioning\UnavailableDomainProvisioner;
use App\FaithFlow\FaithFlowAi;
use App\InvitationDelivery\InvitationDeliveryTransport;
use App\InvitationDelivery\LocalInvitationDeliveryTransport;
use App\Marketplace\Delivery\FilesystemMarketplaceDelivery;
use App\Marketplace\Delivery\MarketplaceDeliveryMechanism;
use App\Marketplace\Entitlements\DefaultMarketplaceEntitlement;
use App\Marketplace\Entitlements\MarketplaceEntitlement;
use App\Models\PlatformAuditEvent;
use App\Models\PlatformMembership;
use App\Policies\PlatformAuditPolicy;
use App\Policies\PlatformStaffPolicy;
use App\PublicWebsite\PublicWebsiteContext;
use App\PublicWebsite\Themes\ThemeRegistry;
use App\Support\OrganizationContext;
use App\Support\PlatformContext;
use App\Support\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // TenantContext is a request/execution-scoped security boundary,
        // not application-global state. scoped() resolves once per
        // container lifecycle and is flushed automatically between
        // requests under Octane (Container::forgetScopedInstances()) —
        // singleton() would not be. See K-IDENTITY-001B-R1 §4/§5.
        $this->app->scoped(TenantContext::class);
        $this->app->scoped(OrganizationContext::class);
        $this->app->scoped(PlatformContext::class);

        // Public visitors have no membership and must never be projected
        // into TenantContext. This separate scoped boundary is resolved
        // once per request from a first-party Website host.
        $this->app->scoped(PublicWebsiteContext::class);
        $this->app->singleton(ThemeRegistry::class);
        $this->app->bind(ProductEntitlementSource::class, SubscriptionEntitlementSource::class);

        // FaithFlow's own provider boundary — see K-FAITHFLOW-001B §17/§47.
        // Stateless, so a plain singleton (not scoped) is correct.
        $this->app->singleton(FaithFlowAi::class);
        $this->app->bind(DesignRenderer::class, PlaywrightDesignRenderer::class);
        $this->app->bind(MarketplaceEntitlement::class, DefaultMarketplaceEntitlement::class);
        $this->app->bind(MarketplaceDeliveryMechanism::class, FilesystemMarketplaceDelivery::class);
        $this->app->bind(InvitationDeliveryTransport::class, LocalInvitationDeliveryTransport::class);
        $this->app->bind(DnsResolver::class, function ($app): DnsResolver {
            $driver = config('public-website.custom_domains.dns_resolver');
            if ($driver === 'fake' && ! $app->environment('production')) {
                return $app->make(FakeDnsResolver::class);
            }
            if ($driver === 'cloudflare') {
                return $app->make(CloudflareDnsResolver::class);
            }

            return $app->make(UnavailableDnsResolver::class);
        });
        $this->app->bind(DomainProvisioner::class, function ($app): DomainProvisioner {
            $driver = config('public-website.custom_domains.provisioner');
            if ($driver === 'fake' && ! $app->environment('production')) {
                return $app->make(FakeDomainProvisioner::class);
            }
            if ($driver === 'cloudflare') {
                return $app->make(CloudflareDomainProvisioner::class);
            }

            return $app->make(UnavailableDomainProvisioner::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(PlatformMembership::class, PlatformStaffPolicy::class);
        Gate::policy(PlatformAuditEvent::class, PlatformAuditPolicy::class);

        // Keryon-internal safety bound on provider-consuming domain jobs
        // (initial verification, TLS polling, ongoing health checks) — not
        // an attempt to mirror Cloudflare's own published quota. A job
        // rate-limited here is released, not failed: it must never count
        // as a domain-health failure. See K-DOMAIN-001E §23.
        RateLimiter::for('domain-provider', fn () => Limit::perMinute(
            (int) config('public-website.custom_domains.provider_rate_limit_per_minute', 30)
        ));
    }
}
