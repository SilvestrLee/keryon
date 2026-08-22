<?php

namespace App\Providers;

use App\Design\Rendering\DesignRenderer;
use App\Design\Rendering\PlaywrightDesignRenderer;
use App\FaithFlow\FaithFlowAi;
use App\PublicWebsite\PublicWebsiteContext;
use App\PublicWebsite\Themes\ThemeRegistry;
use App\Support\TenantContext;
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

        // Public visitors have no membership and must never be projected
        // into TenantContext. This separate scoped boundary is resolved
        // once per request from a first-party Website host.
        $this->app->scoped(PublicWebsiteContext::class);
        $this->app->singleton(ThemeRegistry::class);

        // FaithFlow's own provider boundary — see K-FAITHFLOW-001B §17/§47.
        // Stateless, so a plain singleton (not scoped) is correct.
        $this->app->singleton(FaithFlowAi::class);
        $this->app->bind(DesignRenderer::class, PlaywrightDesignRenderer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
