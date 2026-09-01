<?php

namespace App\Enums;

enum PlatformAuditEventType: string
{
    case PLATFORM_ACCESS_GRANTED = 'platform.access.granted';
    case PLATFORM_ACCESS_SUSPENDED = 'platform.access.suspended';
    case PLATFORM_ACCESS_REMOVED = 'platform.access.removed';
    case CHURCH_PROVISIONED = 'church.provisioned';
    case ACTIVATION_INVITATION_RESENT = 'activation.invitation.resent';
    case ACTIVATION_REVOKED = 'activation.revoked';
    case DOMAIN_VERIFICATION_RETRY_REQUESTED = 'domain.verification.retry_requested';
}
