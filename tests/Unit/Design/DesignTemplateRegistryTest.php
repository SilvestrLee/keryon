<?php

namespace Tests\Unit\Design;

use App\Design\Templates\DesignTemplateRegistry;
use App\Design\Templates\Reference\SundayModernReference;
use App\Enums\DesignOutputFormat;
use App\Enums\DesignPurpose;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class DesignTemplateRegistryTest extends TestCase
{
    public function test_reference_template_registration_and_format_contract(): void
    {
        $template = (new DesignTemplateRegistry)->resolve('sunday-modern-reference', 1);

        $this->assertSame('sunday-modern-reference@1', $template->identity());
        $this->assertTrue($template->supports(DesignPurpose::SERVICE, DesignOutputFormat::SQUARE));
        $this->assertTrue($template->supports(DesignPurpose::SERVICE, DesignOutputFormat::PORTRAIT, 'minimal'));
        $this->assertTrue($template->supports(DesignPurpose::SERVICE, DesignOutputFormat::STORY));
        $this->assertFalse($template->supports(DesignPurpose::CAMPAIGN, DesignOutputFormat::SQUARE));
        $this->assertSame([1080, 1080], [DesignOutputFormat::SQUARE->width(), DesignOutputFormat::SQUARE->height()]);
        $this->assertSame([1080, 1350], [DesignOutputFormat::PORTRAIT->width(), DesignOutputFormat::PORTRAIT->height()]);
        $this->assertSame([1080, 1920], [DesignOutputFormat::STORY->width(), DesignOutputFormat::STORY->height()]);
    }

    public function test_duplicate_template_version_is_rejected(): void
    {
        $definition = SundayModernReference::definition();
        $registry = new DesignTemplateRegistry([$definition]);

        $this->expectException(LogicException::class);
        $registry->register($definition);
    }

    public function test_missing_template_version_fails_safely(): void
    {
        $this->expectException(LogicException::class);
        (new DesignTemplateRegistry)->resolve('sunday-modern-reference', 99);
    }

    public function test_required_optional_and_length_contracts_are_canonical(): void
    {
        $template = SundayModernReference::definition();
        $normalized = $template->validateInputs([
            'title' => '  Sunday Worship  ',
            'date' => '2026-08-23',
            'time' => '09:30',
        ]);

        $this->assertSame([
            'date' => '2026-08-23',
            'time' => '09:30',
            'title' => 'Sunday Worship',
        ], $normalized);

        try {
            $template->validateInputs(['title' => 'Sunday Worship', 'date' => '2026-08-23']);
            $this->fail('Missing required slot was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('inputs.time', $exception->errors());
        }

        $this->expectException(ValidationException::class);
        $template->validateInputs([
            'title' => str_repeat('x', 73),
            'date' => '2026-08-23',
            'time' => '09:30',
        ]);
    }
}
