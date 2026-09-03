<?php

namespace App\Enums;

enum OrganizationAuditSubjectType: string
{
    case ORGANIZATION = 'organization';
    case UNIT = 'organization_unit';
    case CHURCH_ASSIGNMENT = 'church_organization_assignment';
    case MEMBERSHIP = 'organization_membership';
    case ROLE_ASSIGNMENT = 'organization_role_assignment';
    case COMMUNICATION = 'organization_communication';
    case COMMUNICATION_REVISION = 'organization_communication_revision';
    case COMMUNICATION_ASSET = 'organization_communication_asset';
}
