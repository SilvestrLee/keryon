<?php

namespace App\Enums;

// The approved FaithFlow MVP output catalogue — see
// Keryon_Blueprint_v1.4_FaithFlow_MVP_Addendum.md §6 and K-FAITHFLOW-001B
// §3.4/§12. Do not add cases here without separate Product Office approval;
// the catalogue is locked.
enum FaithFlowOutputType: string
{
    case SERMON_SUMMARY = 'sermon_summary';
    case KEY_THEMES = 'key_themes';
    case KEY_QUOTES = 'key_quotes';
    case DEVOTIONAL = 'devotional';
    case PRAYER_POINTS = 'prayer_points';
    case SOCIAL_CAPTIONS = 'social_captions';
    case WHATSAPP_STATUS_COPY = 'whatsapp_status_copy';
    case DISCUSSION_QUESTIONS = 'discussion_questions';

    public function label(): string
    {
        return match ($this) {
            self::SERMON_SUMMARY => 'Sermon Summary',
            self::KEY_THEMES => 'Key Themes',
            self::KEY_QUOTES => 'Key Quotes',
            self::DEVOTIONAL => 'Devotional',
            self::PRAYER_POINTS => 'Prayer Points',
            self::SOCIAL_CAPTIONS => 'Social Captions',
            self::WHATSAPP_STATUS_COPY => 'WhatsApp / Status Copy',
            self::DISCUSSION_QUESTIONS => 'Discussion Questions',
        };
    }

    /**
     * The single authoritative output-type -> ContentType mapping seam — see
     * K-FAITHFLOW-001A §12/§25 and K-FAITHFLOW-001B §3.5/§28. Key Themes and
     * Key Quotes are FaithFlow-native reference/intelligence outputs and are
     * deliberately not publishable Content Studio communications — they
     * return null rather than an invented mapping.
     */
    public function contentType(): ?ContentType
    {
        return match ($this) {
            self::SERMON_SUMMARY => ContentType::SERMON_SUMMARY,
            self::DEVOTIONAL => ContentType::DEVOTIONAL,
            self::PRAYER_POINTS => ContentType::PRAYER_POINTS,
            self::SOCIAL_CAPTIONS => ContentType::SOCIAL_CAPTION,
            self::WHATSAPP_STATUS_COPY => ContentType::WHATSAPP_STATUS_COPY,
            self::DISCUSSION_QUESTIONS => ContentType::DISCUSSION_QUESTIONS,
            self::KEY_THEMES, self::KEY_QUOTES => null,
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->all();
    }

    /**
     * Which generation Agent class (and rendering strategy) this output uses
     * — see K-FAITHFLOW-001D "Agent/prompt architecture".
     */
    public function resultShape(): FaithFlowOutputResultShape
    {
        return match ($this) {
            self::SERMON_SUMMARY, self::DEVOTIONAL, self::WHATSAPP_STATUS_COPY => FaithFlowOutputResultShape::TEXT,
            self::KEY_THEMES, self::KEY_QUOTES, self::PRAYER_POINTS, self::SOCIAL_CAPTIONS, self::DISCUSSION_QUESTIONS => FaithFlowOutputResultShape::LIST,
        };
    }
}
