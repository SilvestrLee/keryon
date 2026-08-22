<?php

namespace App\Enums;

enum ContentStatus: string
{
    case DRAFT = 'draft';
    case REVIEW = 'review';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::REVIEW => 'In Review',
            self::APPROVED => 'Approved',
            // Stored value remains "rejected"; the human-facing label is
            // softer per K-CONTENT-002 §7 — this is feedback to act on,
            // not a final rejection.
            self::REJECTED => 'Needs Changes',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::REVIEW => 'warning',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->all();
    }
}
