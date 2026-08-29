<?php

namespace App\Enums;

enum OrganizationCapability: string
{
    case OrganizationView = 'organization.view';
    case OrganizationManage = 'organization.manage';
    case UnitsView = 'organization.units.view';
    case UnitsManage = 'organization.units.manage';
    case ChurchesView = 'organization.churches.view';
    case ChurchesManageAssignments = 'organization.churches.manage_assignments';
    case MembershipsView = 'organization.memberships.view';
    case MembershipsManage = 'organization.memberships.manage';
}
