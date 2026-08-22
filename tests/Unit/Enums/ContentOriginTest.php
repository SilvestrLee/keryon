<?php

namespace Tests\Unit\Enums;

use App\Enums\ContentOrigin;
use PHPUnit\Framework\TestCase;

class ContentOriginTest extends TestCase
{
    public function test_expected_cases_exist(): void
    {
        $values = array_map(fn (ContentOrigin $case) => $case->value, ContentOrigin::cases());

        $this->assertSame(['human', 'faithflow'], $values);
    }

    public function test_every_case_has_a_label(): void
    {
        foreach (ContentOrigin::cases() as $case) {
            $this->assertNotSame('', $case->label());
        }
    }

    public function test_options_maps_value_to_label(): void
    {
        $this->assertSame([
            'human' => 'Human',
            'faithflow' => 'FaithFlow',
        ], ContentOrigin::options());
    }
}
