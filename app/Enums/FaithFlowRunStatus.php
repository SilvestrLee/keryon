<?php

namespace App\Enums;

// Run-level lifecycle only — see K-FAITHFLOW-001B §10. Generation-level
// concerns (per output-type progress) belong to FaithFlowOutputStatus, not
// here, so this stays intentionally small: a run either hasn't been
// analyzed yet, is being analyzed, has a usable canonical analysis, or the
// last analysis attempt failed.
enum FaithFlowRunStatus: string
{
    case DRAFT = 'draft';
    case ANALYZING = 'analyzing';
    case ANALYZED = 'analyzed';
    case ANALYSIS_FAILED = 'analysis_failed';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::ANALYZING => 'Analyzing',
            self::ANALYZED => 'Analyzed',
            self::ANALYSIS_FAILED => 'Analysis Failed',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->all();
    }
}
