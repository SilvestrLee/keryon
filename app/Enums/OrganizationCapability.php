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
    case CommunicationsView = 'organization.communications.view';
    case CommunicationsCreate = 'organization.communications.create';
    case CommunicationsEdit = 'organization.communications.edit';
    case CommunicationsApprove = 'organization.communications.approve';
    case CommunicationsDistribute = 'organization.communications.distribute';
    case CommunicationsWithdraw = 'organization.communications.withdraw';
    case CommunicationDeliveriesView = 'organization.communications.deliveries.view';
}
