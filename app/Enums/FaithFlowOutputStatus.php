<?php

namespace App\Enums;

// See K-FAITHFLOW-001B §13. One row per selected output type moves through
// this lifecycle independently of every other output on the same run — this
// is what makes partial failure (K-FAITHFLOW-001A §26) fall out of the data
// model for free instead of needing special-case code later.
enum FaithFlowOutputStatus: string
{
    case PENDING = 'pending';
    case GENERATING = 'generating';
    case GENERATED = 'generated';
    case FAILED = 'failed';
    case APPROVED = 'approved';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::GENERATING => 'Generating',
            self::GENERATED => 'Generated',
            self::FAILED => 'Failed',
            self::APPROVED => 'Approved',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->all();
    }
}
