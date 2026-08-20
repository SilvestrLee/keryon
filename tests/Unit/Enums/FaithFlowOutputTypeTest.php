<?php

namespace Tests\Unit\Enums;

use App\Enums\ContentType;
use App\Enums\FaithFlowOutputType;
use PHPUnit\Framework\TestCase;

class FaithFlowOutputTypeTest extends TestCase
{
    public function test_expected_cases_exist(): void
    {
        $values = array_map(fn (FaithFlowOutputType $case) => $case->value, FaithFlowOutputType::cases());

        $this->assertSame([
            'sermon_summary',
            'key_themes',
            'key_quotes',
            'devotional',
            'prayer_points',
            'social_captions',
            'whatsapp_status_copy',
            'discussion_questions',
        ], $values);
    }

    public function test_every_case_has_a_label(): void
    {
        foreach (FaithFlowOutputType::cases() as $case) {
            $this->assertNotSame('', $case->label());
        }
    }

    public function test_options_maps_value_to_label(): void
    {
        $options = FaithFlowOutputType::options();

        $this->assertSame('Sermon Summary', $options['sermon_summary']);
        $this->assertSame('WhatsApp / Status Copy', $options['whatsapp_status_copy']);
    }

    public function test_content_type_mapping_resolves_for_publishable_outputs(): void
    {
        $this->assertSame(ContentType::SERMON_SUMMARY, FaithFlowOutputType::SERMON_SUMMARY->contentType());
        $this->assertSame(ContentType::DEVOTIONAL, FaithFlowOutputType::DEVOTIONAL->contentType());
        $this->assertSame(ContentType::PRAYER_POINTS, FaithFlowOutputType::PRAYER_POINTS->contentType());
        $this->assertSame(ContentType::SOCIAL_CAPTION, FaithFlowOutputType::SOCIAL_CAPTIONS->contentType());
        $this->assertSame(ContentType::WHATSAPP_STATUS_COPY, FaithFlowOutputType::WHATSAPP_STATUS_COPY->contentType());
        $this->assertSame(ContentType::DISCUSSION_QUESTIONS, FaithFlowOutputType::DISCUSSION_QUESTIONS->contentType());
    }

    public function test_content_type_mapping_is_null_for_faithflow_native_reference_outputs(): void
    {
        $this->assertNull(FaithFlowOutputType::KEY_THEMES->contentType());
        $this->assertNull(FaithFlowOutputType::KEY_QUOTES->contentType());
    }
}
