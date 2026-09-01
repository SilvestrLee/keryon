<?php

namespace App\Enums;

enum ChurchDomainEventType: string
{
    case Requested = 'requested';
    case VerificationStarted = 'verification_started';
    case VerificationFailed = 'verification_failed';
    case TokenRegenerated = 'token_regenerated';
    case OwnershipVerified = 'ownership_verified';
    case RoutingVerified = 'routing_verified';
    case TlsProvisioning = 'tls_provisioning';
    case TlsReady = 'tls_ready';
    case TlsFailed = 'tls_failed';
    case Activated = 'activated';
    case PrimaryChanged = 'primary_changed';
    case Degraded = 'degraded';
    case Disabled = 'disabled';
    case Released = 'released';
}
