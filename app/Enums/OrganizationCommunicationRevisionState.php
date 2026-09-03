<?php

namespace App\Enums;

enum OrganizationCommunicationRevisionState: string
{
    case DRAFT = 'draft';
    case IN_REVIEW = 'in_review';
    case CHANGES_REQUESTED = 'changes_requested';
    case APPROVED = 'approved';
    case DISTRIBUTED = 'distributed';
}
