<?php

namespace App\Enums;

/**
 * See K-LEGAL-001B-A-ARCHITECTURE.md §3. Declaration order fixes the
 * terms-before-privacy lock ordering specified in Architecture §8.2 for a
 * future milestone's acceptance-flow wiring.
 */
enum PolicyDocumentType: string
{
    case TERMS = 'terms';
    case PRIVACY = 'privacy';
}
