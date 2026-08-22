<?php

namespace App\Design\Templates;

use App\Enums\DesignSlotType;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final readonly class DesignSlot
{
    public function __construct(
        public string $key,
        public string $label,
        public DesignSlotType $type,
        public bool $required = false,
        public ?int $maxCharacters = null,
        public ?int $maxLines = null,
    ) {}

    public function validate(mixed $value): void
    {
        if ($value === null || $value === '') {
            if ($this->required) {
                $this->fail("The {$this->label} slot is required.");
            }

            return;
        }

        if (! is_string($value)) {
            $this->fail("The {$this->label} slot must be text.");
        }

        $value = trim($value);

        if ($this->maxCharacters !== null && mb_strlen($value) > $this->maxCharacters) {
            $this->fail("The {$this->label} slot may not exceed {$this->maxCharacters} characters.");
        }

        if ($this->maxLines !== null && substr_count($value, "\n") + 1 > $this->maxLines) {
            $this->fail("The {$this->label} slot may not exceed {$this->maxLines} lines.");
        }

        if ($this->type === DesignSlotType::DATE && ! $this->isDate($value)) {
            $this->fail("The {$this->label} slot must use YYYY-MM-DD format.");
        }

        if ($this->type === DesignSlotType::TIME && ! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value)) {
            $this->fail("The {$this->label} slot must use 24-hour HH:MM format.");
        }
    }

    private function isDate(string $value): bool
    {
        try {
            return Carbon::createFromFormat('Y-m-d', $value)->format('Y-m-d') === $value;
        } catch (\Throwable) {
            return false;
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(["inputs.{$this->key}" => $message]);
    }
}
