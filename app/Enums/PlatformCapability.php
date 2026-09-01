<?php

namespace App\Enums;

enum PlatformCapability: string
{
    case PlatformHomeView = 'platform.home.view';
    case ChurchesView = 'platform.churches.view';
    case ChurchesProvision = 'platform.churches.provision';
    case ActivationsView = 'platform.activations.view';
    case ActivationsManage = 'platform.activations.manage';
    case OrganizationsView = 'platform.organizations.view';
    case OrganizationsProvision = 'platform.organizations.provision';
    case AssignmentsView = 'platform.assignments.view';
    case SubscriptionsView = 'platform.subscriptions.view';
    case SubscriptionsManage = 'platform.subscriptions.manage';
    case PricingView = 'platform.pricing.view';
    case PricingManage = 'platform.pricing.manage';
    case BillingView = 'platform.billing.view';
    case BillingManage = 'platform.billing.manage';
    case DomainsView = 'platform.domains.view';
    case DomainsManage = 'platform.domains.manage';
    case DeliveriesView = 'platform.deliveries.view';
    case DeliveriesManage = 'platform.deliveries.manage';
    case ProvidersView = 'platform.providers.view';
    case ProvidersManage = 'platform.providers.manage';
    case TrustView = 'platform.trust.view';
    case TrustManage = 'platform.trust.manage';
    case PlatformStaffView = 'platform.staff.view';
    case PlatformStaffManage = 'platform.staff.manage';
    case PlatformAuditView = 'platform.audit.view';
}
