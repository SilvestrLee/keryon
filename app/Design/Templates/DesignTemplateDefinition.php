<?php

namespace App\Design\Templates;

use App\Enums\DesignOutputFormat;
use App\Enums\DesignPurpose;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class DesignTemplateDefinition
{
    /**
     * @param  list<DesignPurpose>  $purposes
     * @param  list<DesignOutputFormat>  $formats
     * @param  list<DesignSlot>  $slots
     * @param  list<DesignImageSlot>  $imageSlots
     * @param  list<string>  $variants
     */
    public function __construct(
        public string $key,
        public int $version,
        public string $name,
        public string $family,
        public int $familyVersion,
        public array $purposes,
        public array $formats,
        public array $slots,
        public array $imageSlots,
        public DesignBrandRules $brand,
        public array $variants = ['default'],
    ) {
        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $key) || $version < 1 || $familyVersion < 1) {
            throw new InvalidArgumentException('Template identity must use a stable kebab-case key and positive versions.');
        }

        $this->assertUniqueKeys($slots, 'content slot');
        $this->assertUniqueKeys($imageSlots, 'image slot');

        if ($formats === [] || $purposes === [] || $variants === []) {
            throw new InvalidArgumentException('A template must declare purposes, formats, and at least one variant.');
        }
    }

    public function identity(): string
    {
        return "{$this->key}@{$this->version}";
    }

    public function supports(DesignPurpose $purpose, DesignOutputFormat $format, string $variant = 'default'): bool
    {
        return in_array($purpose, $this->purposes, true)
            && in_array($format, $this->formats, true)
            && in_array($variant, $this->variants, true);
    }

    /** @param array<string, mixed> $inputs */
    public function validateInputs(array $inputs): array
    {
        $known = array_map(fn (DesignSlot $slot): string => $slot->key, $this->slots);
        $unknown = array_diff(array_keys($inputs), $known);

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'inputs' => 'Unknown template slots: '.implode(', ', $unknown).'.',
            ]);
        }

        $normalized = [];

        foreach ($this->slots as $slot) {
            $slot->validate($inputs[$slot->key] ?? null);
            $value = $inputs[$slot->key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $normalized[$slot->key] = trim($value);
            }
        }

        ksort($normalized);

        return $normalized;
    }

    private function assertUniqueKeys(array $definitions, string $label): void
    {
        $keys = array_map(fn ($definition): string => $definition->key, $definitions);

        if (count($keys) !== count(array_unique($keys))) {
            throw new InvalidArgumentException("Duplicate {$label} keys are not allowed.");
        }
    }
}
