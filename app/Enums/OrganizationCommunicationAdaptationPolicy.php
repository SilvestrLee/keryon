<?php

namespace App\Enums;

enum OrganizationCommunicationAdaptationPolicy: string
{
    case USE_AS_PROVIDED = 'use_as_provided';
    case LOCAL_ADAPTATION_ENCOURAGED = 'local_adaptation_encouraged';
    case LOCAL_DETAILS_REQUIRED = 'local_details_required';
    case REFERENCE_ONLY = 'reference_only';
}
