<?php

namespace App\Commercial\Entitlements;

use App\Enums\EntitlementValueType;
use LogicException;

final readonly class EntitlementValue
{
    private function __construct(
        public EntitlementValueType $type,
        private bool|int $value,
    ) {}

    public static function boolean(bool $value): self
    {
        return new self(EntitlementValueType::BOOLEAN, $value);
    }

    public static function integer(int $value): self
    {
        return new self(EntitlementValueType::INTEGER, $value);
    }

    public function booleanValue(): bool
    {
        if ($this->type !== EntitlementValueType::BOOLEAN) {
            throw new LogicException('This entitlement value is not boolean.');
        }

        return $this->value;
    }

    public function integerValue(): int
    {
        if ($this->type !== EntitlementValueType::INTEGER) {
            throw new LogicException('This entitlement value is not integer.');
        }

        return $this->value;
    }

    public function raw(): bool|int
    {
        return $this->value;
    }
}
