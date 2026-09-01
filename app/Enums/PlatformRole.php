<?php

namespace App\Enums;

enum PlatformRole: string
{
    case ADMINISTRATOR = 'administrator';
    case OPERATIONS = 'operations';
    case SUPPORT = 'support';
    case COMMERCIAL = 'commercial';
    case TRUST_SECURITY = 'trust_security';

    /** @return list<PlatformCapability> */
    public function capabilities(): array
    {
        return match ($this) {
            self::ADMINISTRATOR => PlatformCapability::cases(),
            self::OPERATIONS => [
                PlatformCapability::PlatformHomeView, PlatformCapability::ChurchesView,
                PlatformCapability::ChurchesProvision, PlatformCapability::ActivationsView,
                PlatformCapability::ActivationsManage, PlatformCapability::OrganizationsView,
                PlatformCapability::OrganizationsProvision, PlatformCapability::AssignmentsView,
                PlatformCapability::SubscriptionsView, PlatformCapability::PricingView,
                PlatformCapability::DomainsView, PlatformCapability::DomainsManage,
                PlatformCapability::DeliveriesView, PlatformCapability::DeliveriesManage,
                PlatformCapability::ProvidersView, PlatformCapability::PlatformAuditView,
                PlatformCapability::PlatformChurchProvision, PlatformCapability::PlatformActivationResend,
                PlatformCapability::PlatformActivationRevoke, PlatformCapability::PlatformDomainRetry,
            ],
            self::SUPPORT => [
                PlatformCapability::PlatformHomeView, PlatformCapability::ChurchesView,
                PlatformCapability::ActivationsView, PlatformCapability::OrganizationsView,
                PlatformCapability::AssignmentsView, PlatformCapability::DomainsView,
                PlatformCapability::DeliveriesView, PlatformCapability::ProvidersView,
                PlatformCapability::PlatformActivationResend, PlatformCapability::PlatformDomainRetry,
            ],
            self::COMMERCIAL => [
                PlatformCapability::PlatformHomeView, PlatformCapability::ChurchesView,
                PlatformCapability::ActivationsView, PlatformCapability::ActivationsManage,
                PlatformCapability::OrganizationsView, PlatformCapability::AssignmentsView,
                PlatformCapability::SubscriptionsView, PlatformCapability::SubscriptionsManage,
                PlatformCapability::PricingView, PlatformCapability::PricingManage,
                PlatformCapability::BillingView, PlatformCapability::BillingManage,
                PlatformCapability::ProvidersView, PlatformCapability::PlatformAuditView,
            ],
            self::TRUST_SECURITY => [
                PlatformCapability::PlatformHomeView, PlatformCapability::DomainsView,
                PlatformCapability::ProvidersView, PlatformCapability::ProvidersManage,
                PlatformCapability::TrustView, PlatformCapability::TrustManage,
                PlatformCapability::PlatformAuditView,
            ],
        };
    }

    public function hasCapability(PlatformCapability $capability): bool
    {
        return in_array($capability, $this->capabilities(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::ADMINISTRATOR => 'Platform Administrator',
            self::OPERATIONS => 'Operations',
            self::SUPPORT => 'Support',
            self::COMMERCIAL => 'Commercial',
            self::TRUST_SECURITY => 'Trust & Security',
        };
    }
}
