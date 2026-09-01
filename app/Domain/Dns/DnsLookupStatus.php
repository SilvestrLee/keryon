<?php

namespace App\Domain\Dns;

enum DnsLookupStatus: string
{
    case Found = 'found';
    case NotFound = 'not_found';
    case Timeout = 'timeout';
    case Unavailable = 'unavailable';
}
