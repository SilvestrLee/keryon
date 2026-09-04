<?php

namespace App\Enums;

/**
 * K-ORG-COMMS-001C §7/§67 — bounded targeting modes using existing
 * hierarchy semantics only. No demographic targeting, no congregation
 * data.
 */
enum OrganizationCommunicationTargetMode: string
{
    case GOVERNING_SCOPE = 'governing_scope';
    case UNIT_SUBTREE = 'unit_subtree';
    case EXPLICIT_CHURCHES = 'explicit_churches';

    public function label(): string
    {
        return match ($this) {
            self::GOVERNING_SCOPE => 'Entire governing scope',
            self::UNIT_SUBTREE => 'Selected Unit',
            self::EXPLICIT_CHURCHES => 'Selected Churches',
        };
    }
}
