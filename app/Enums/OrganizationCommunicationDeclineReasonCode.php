<?php

namespace App\Enums;

/**
 * K-ORG-COMMS-001D §13 — locked, fixed decline-reason vocabulary. Optional
 * on decline. Never exposed to the Organization as free text — "other"
 * carries no Organization-visible note in MVP.
 */
enum OrganizationCommunicationDeclineReasonCode: string
{
    case NOT_RELEVANT = 'not_relevant';
    case TIMING_NOT_SUITABLE = 'timing_not_suitable';
    case ALREADY_COVERED_LOCALLY = 'already_covered_locally';
    case REQUIRES_LOCAL_REVIEW = 'requires_local_review';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::NOT_RELEVANT => 'Not relevant to our Church',
            self::TIMING_NOT_SUITABLE => 'Timing is not suitable',
            self::ALREADY_COVERED_LOCALLY => 'Already covered locally',
            self::REQUIRES_LOCAL_REVIEW => 'Requires local review first',
            self::OTHER => 'Other',
        };
    }
}
