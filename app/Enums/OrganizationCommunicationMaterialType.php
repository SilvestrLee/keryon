<?php

namespace App\Enums;

enum OrganizationCommunicationMaterialType: string
{
    case GENERAL = 'general';
    case ANNOUNCEMENT = 'announcement';
    case SOCIAL_CAPTION = 'social_caption';
    case DEVOTIONAL = 'devotional';
    case PRAYER_POINTS = 'prayer_points';
    case DISCUSSION_QUESTIONS = 'discussion_questions';
    case CAMPAIGN_COPY = 'campaign_copy';
    case WEBSITE_COPY = 'website_copy';
    case WHATSAPP_STATUS_COPY = 'whatsapp_status_copy';
}
