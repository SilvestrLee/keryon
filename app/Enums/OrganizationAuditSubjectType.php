<?php

namespace App\Enums;

enum OrganizationAuditSubjectType: string
{
    case ORGANIZATION = 'organization';
    case UNIT = 'organization_unit';
    case CHURCH_ASSIGNMENT = 'church_organization_assignment';
}
