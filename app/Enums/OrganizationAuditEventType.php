<?php

namespace App\Enums;

enum OrganizationAuditEventType: string
{
    case ORGANIZATION_CREATED = 'organization.created';
    case UNIT_CREATED = 'organization.unit.created';
    case UNIT_MOVED = 'organization.unit.moved';
    case UNIT_ARCHIVED = 'organization.unit.archived';
    case ATTACHMENT_REQUESTED = 'organization.church.attachment_requested';
    case ATTACHMENT_ACCEPTED = 'organization.church.attachment_accepted';
    case ATTACHMENT_REJECTED = 'organization.church.attachment_rejected';
    case CHURCH_MOVED = 'organization.church.moved';
    case CHURCH_DETACHED = 'organization.church.detached';
    case MEMBERSHIP_CREATED = 'organization.membership.created';
    case MEMBERSHIP_INVITED = 'organization.membership.invited';
    case MEMBERSHIP_ACTIVATED = 'organization.membership.activated';
    case MEMBERSHIP_SUSPENDED = 'organization.membership.suspended';
    case MEMBERSHIP_REMOVED = 'organization.membership.removed';
    case ROLE_ASSIGNED = 'organization.role.assigned';
    case ROLE_SUSPENDED = 'organization.role.suspended';
    case ROLE_REMOVED = 'organization.role.removed';
    case ROLE_SCOPE_CHANGED = 'organization.role.scope_changed';
    case COMMUNICATION_CREATED = 'organization.communication.created';
    case COMMUNICATION_REVISION_CREATED = 'organization.communication.revision_created';
    case COMMUNICATION_REVISION_SUBMITTED = 'organization.communication.revision_submitted';
    case COMMUNICATION_REVISION_CHANGES_REQUESTED = 'organization.communication.revision_changes_requested';
    case COMMUNICATION_REVISION_APPROVED = 'organization.communication.revision_approved';
    case COMMUNICATION_WITHDRAWN = 'organization.communication.withdrawn';
    case COMMUNICATION_CLOSED = 'organization.communication.closed';
    case COMMUNICATION_ASSET_ADDED = 'organization.communication.asset_added';
    case COMMUNICATION_ASSET_REMOVED = 'organization.communication.asset_removed';
    case COMMUNICATION_DISTRIBUTION_REQUESTED = 'organization.communication.distribution_requested';
    case COMMUNICATION_DISTRIBUTION_COMPLETED = 'organization.communication.distribution_completed';
    case COMMUNICATION_DISTRIBUTION_FAILED = 'organization.communication.distribution_failed';
}
