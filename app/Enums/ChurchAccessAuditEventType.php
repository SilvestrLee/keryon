<?php

namespace App\Enums;

enum ChurchAccessAuditEventType: string
{
    case STAFF_INVITED = 'staff.invited';
    case STAFF_INVITATION_RESENT = 'staff.invitation_resent';
    case STAFF_INVITATION_REVOKED = 'staff.invitation_revoked';
    case STAFF_JOINED = 'staff.joined';
    case STAFF_SUSPENDED = 'staff.suspended';
    case STAFF_REACTIVATED = 'staff.reactivated';
    case STAFF_REMOVED = 'staff.removed';
    case STAFF_ROLES_CHANGED = 'staff.roles_changed';
    case PRIMARY_TRANSFERRED = 'primary.transferred';
}
