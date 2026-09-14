<?php

namespace App\Domain;

use App\Commercial\Entitlements\EntitlementResolver;
use App\Enums\EntitlementKey;
use App\Models\Church;
use App\Models\ChurchDomain;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * The canonical custom-domain commercial-entitlement guard — K-DOMAIN-001F
 * §7. Deliberately separate from DomainMutationAuthorizer: authorization
 * (who may manage this Church's domains) and commercial entitlement
 * (whether this Church's plan includes custom domains at all) are
 * different concerns, and cleanup actions (disable/release) must remain
 * available even when this guard would deny (§2.4/§8) — so this guard is
 * never consulted by DisableChurchDomain or ReleaseChurchDomain.
 *
 * Entitlement-consuming actions only: claiming, regenerating a setup
 * token, starting/restarting verification, and making primary.
 */
final readonly class CustomDomainEntitlementGuard
{
    public function __construct(
        private EntitlementResolver $entitlements,
        private TenantContext $tenant,
    ) {}

    public function assertAllowed(Church|ChurchDomain $subject): void
    {
        if (! $this->allows($subject)) {
            throw new AuthorizationException("Custom domains are not available for this Church's current plan.");
        }
    }

    public function allows(Church|ChurchDomain $subject): bool
    {
        $church = $this->resolveChurch($subject);

        return $church !== null && $this->entitlements->allows($church, EntitlementKey::WebsiteCustomDomainEnabled);
    }

    private function resolveChurch(Church|ChurchDomain $subject): ?Church
    {
        // A ChurchDomain caller has always just passed DomainMutationAuthorizer
        // ::authorize(), which already proved the current membership's
        // church_id matches the domain's — so the current TenantContext
        // Church is the correct, already-resolved subject here.
        return $subject instanceof Church ? $subject : $this->tenant->currentChurch();
    }
}
