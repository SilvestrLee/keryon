<?php

namespace App\Enums;

enum PlatformAuditReasonCategory: string
{
    case CUSTOMER_REQUEST = 'customer_request';
    case DELIVERY_RECOVERY = 'delivery_recovery';
    case COMMERCIAL_CORRECTION = 'commercial_correction';
    case SECURITY_RESPONSE = 'security_response';
    case PROVIDER_RECOVERY = 'provider_recovery';
    case DATA_CORRECTION = 'data_correction';
    case PLATFORM_ADMINISTRATION = 'platform_administration';
}
