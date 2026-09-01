<?php

namespace App\Enums;

enum DomainFailureCode: string
{
    case InvalidHostname = 'invalid_hostname';
    case PlatformHost = 'platform_host';
    case Conflict = 'conflict';
    case DnsNotConfigured = 'dns_not_configured';
    case DnsMismatch = 'dns_mismatch';
    case DnsLookupTimeout = 'dns_lookup_timeout';
    case VerificationExpired = 'verification_expired';
    case CertificatePending = 'certificate_pending';
    case CertificateFailed = 'certificate_failed';
    case ProviderUnavailable = 'provider_unavailable';
    case Disabled = 'disabled';
}
