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
}
