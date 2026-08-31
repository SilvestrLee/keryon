<?php

namespace App\Enums;

enum InvitationDeliverySubjectType: string
{
    case CHURCH_ACTIVATION = 'church_activation';
    case CHURCH_STAFF_INVITATION = 'church_staff_invitation';
}
