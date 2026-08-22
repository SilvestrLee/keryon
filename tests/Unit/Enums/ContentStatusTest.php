<?php

namespace Tests\Unit\Enums;

use App\Enums\ContentStatus;
use PHPUnit\Framework\TestCase;

class ContentStatusTest extends TestCase
{
    public function test_expected_cases_exist(): void
    {
        $values = array_map(fn (ContentStatus $case) => $case->value, ContentStatus::cases());

        $this->assertSame(['draft', 'review', 'approved', 'rejected'], $values);
    }

    public function test_stored_values_are_stable(): void
    {
        $this->assertSame('draft', ContentStatus::DRAFT->value);
        $this->assertSame('review', ContentStatus::REVIEW->value);
        $this->assertSame('approved', ContentStatus::APPROVED->value);
        $this->assertSame('rejected', ContentStatus::REJECTED->value);
    }

    public function test_rejected_has_a_softer_human_facing_label(): void
    {
        $this->assertSame('Needs Changes', ContentStatus::REJECTED->label());
    }

    public function test_every_case_has_a_label(): void
    {
        foreach (ContentStatus::cases() as $case) {
            $this->assertNotSame('', $case->label());
        }
    }

    public function test_every_case_has_a_color(): void
    {
        foreach (ContentStatus::cases() as $case) {
            $this->assertNotSame('', $case->color());
        }
    }

    public function test_options_maps_value_to_label(): void
    {
        $this->assertSame([
            'draft' => 'Draft',
            'review' => 'In Review',
            'approved' => 'Approved',
            'rejected' => 'Needs Changes',
        ], ContentStatus::options());
    }
}
