<?php

namespace App\Enums;

enum PublicWebsiteHostType: string
{
    case KeryonSubdomain = 'keryon_subdomain';
    case CustomDomain = 'custom_domain';
}
