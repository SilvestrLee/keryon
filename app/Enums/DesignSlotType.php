<?php

namespace App\Enums;

enum DesignSlotType: string
{
    case SHORT_TEXT = 'short_text';
    case LONG_TEXT = 'long_text';
    case DATE = 'date';
    case TIME = 'time';
    case SCRIPTURE = 'scripture';
    case CALL_TO_ACTION = 'call_to_action';
    case CHURCH_IDENTITY = 'church_identity';
    case IMAGE = 'image';
    case LOGO = 'logo';
    case SOCIAL_HANDLE = 'social_handle';
    case LOCATION = 'location';
}
