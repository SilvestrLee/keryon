<?php

namespace App\Enums;

enum OrganizationCommunicationAdaptationPolicy: string
{
    case USE_AS_PROVIDED = 'use_as_provided';
    case LOCAL_ADAPTATION_ENCOURAGED = 'local_adaptation_encouraged';
    case LOCAL_DETAILS_REQUIRED = 'local_details_required';
    case REFERENCE_ONLY = 'reference_only';

    public function label(): string
    {
        return match ($this) {
            self::USE_AS_PROVIDED => 'Use as provided',
            self::LOCAL_ADAPTATION_ENCOURAGED => 'Local adaptation encouraged',
            self::LOCAL_DETAILS_REQUIRED => 'Local details required',
            self::REFERENCE_ONLY => 'Reference material only',
        };
    }

    public function helperText(): string
    {
        return match ($this) {
            self::USE_AS_PROVIDED => 'Churches are encouraged to use this exactly as written.',
            self::LOCAL_ADAPTATION_ENCOURAGED => 'Churches may customize wording and local details while keeping the core message.',
            self::LOCAL_DETAILS_REQUIRED => 'Churches should add their own local details before using this.',
            self::REFERENCE_ONLY => 'Churches may use this as guidance. Direct import will not be available.',
        };
    }
}
