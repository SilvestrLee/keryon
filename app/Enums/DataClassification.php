<?php

namespace App\Enums;

enum DataClassification: string
{
    case Public = 'public';
    case Internal = 'internal';
    case Personal = 'personal';
    case Sensitive = 'sensitive';
    case HighlyRestricted = 'highly_restricted';
}
