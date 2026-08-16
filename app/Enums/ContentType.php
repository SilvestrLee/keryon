<?php

namespace App\Enums;

// Classification only — see K-CONTENT-002 §10. campaign_copy and
// website_copy describe content intent, not a relationship to the
// (not yet implemented) Campaigns or Website Content modules.
enum ContentType: string
{
    case GENERAL = 'general';
    case ANNOUNCEMENT = 'announcement';
    case SOCIAL_CAPTION = 'social_caption';
    case DEVOTIONAL = 'devotional';
    case PRAYER_POINTS = 'prayer_points';
    case DISCUSSION_QUESTIONS = 'discussion_questions';
    case CAMPAIGN_COPY = 'campaign_copy';
    case WEBSITE_COPY = 'website_copy';

    public function label(): string
    {
        return match ($this) {
            self::GENERAL => 'General',
            self::ANNOUNCEMENT => 'Announcement',
            self::SOCIAL_CAPTION => 'Social Caption',
            self::DEVOTIONAL => 'Devotional',
            self::PRAYER_POINTS => 'Prayer Points',
            self::DISCUSSION_QUESTIONS => 'Discussion Questions',
            self::CAMPAIGN_COPY => 'Campaign Copy',
            self::WEBSITE_COPY => 'Website Copy',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->all();
    }
}
