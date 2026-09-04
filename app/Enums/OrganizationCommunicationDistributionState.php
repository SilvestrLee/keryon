<?php

namespace App\Enums;

/**
 * K-ORG-COMMS-001C §10 — bounded lifecycle truthfully representing
 * queue/materialization state. No generic workflow engine.
 */
enum OrganizationCommunicationDistributionState: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::PROCESSING => 'Processing',
            self::COMPLETED => 'Completed',
            self::FAILED => 'Failed',
        };
    }
}
