<?php

namespace App\Enums;

enum OrganizationCommunicationState: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case WITHDRAWN = 'withdrawn';
    case CLOSED = 'closed';
}
