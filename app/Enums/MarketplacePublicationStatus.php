<?php

namespace App\Enums;

enum MarketplacePublicationStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case UNPUBLISHED = 'unpublished';
}
