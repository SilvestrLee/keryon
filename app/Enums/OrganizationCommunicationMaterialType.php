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

    public function label(): string
    {
        return match ($this) {
            self::GENERAL => 'General',
            self::ANNOUNCEMENT => 'Announcement',
            self::SOCIAL_CAPTION => 'Social caption',
            self::DEVOTIONAL => 'Devotional',
            self::PRAYER_POINTS => 'Prayer points',
            self::DISCUSSION_QUESTIONS => 'Discussion questions',
            self::CAMPAIGN_COPY => 'Campaign copy',
            self::WEBSITE_COPY => 'Website copy',
            self::WHATSAPP_STATUS_COPY => 'WhatsApp / status copy',
        };
    }
}
