<?php

namespace Tests\Unit\Enums;

use App\Enums\ContentType;
use PHPUnit\Framework\TestCase;

class ContentTypeTest extends TestCase
{
    public function test_expected_cases_exist(): void
    {
        $values = array_map(fn (ContentType $case) => $case->value, ContentType::cases());

        $this->assertSame([
            'general',
            'announcement',
            'social_caption',
            'devotional',
            'prayer_points',
            'discussion_questions',
            'campaign_copy',
            'website_copy',
            'sermon_summary',
            'whatsapp_status_copy',
        ], $values);
    }

    public function test_every_case_has_a_label(): void
    {
        foreach (ContentType::cases() as $case) {
            $this->assertNotSame('', $case->label());
        }
    }

    public function test_options_maps_value_to_label(): void
    {
        $options = ContentType::options();

        $this->assertSame('General', $options['general']);
        $this->assertSame('Campaign Copy', $options['campaign_copy']);
        $this->assertSame('Website Copy', $options['website_copy']);
        $this->assertSame('Sermon Summary', $options['sermon_summary']);
        $this->assertSame('WhatsApp / Status Copy', $options['whatsapp_status_copy']);
    }
}
