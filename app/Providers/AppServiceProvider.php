<?php

namespace App\Providers;

use App\FaithFlow\FaithFlowAi;
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

        // FaithFlow's own provider boundary — see K-FAITHFLOW-001B §17/§47.
        // Stateless, so a plain singleton (not scoped) is correct.
        $this->app->singleton(FaithFlowAi::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
