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
    case PLATFORM_MFA_ENROLLED = 'platform_mfa.enrolled';
    case PLATFORM_MFA_RECOVERY_REGENERATED = 'platform_mfa.recovery_regenerated';
    case PLATFORM_MFA_RESET = 'platform_mfa.reset';
}
