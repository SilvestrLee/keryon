<?php

namespace App\Communications\OrganizationInbox\Exceptions;

use RuntimeException;

/**
 * K-ORG-COMMS-001D §12/§33/§36/§37/§54 — the factual, church-friendly
 * failure vocabulary for a rejected Accept/Decline attempt. Every message
 * here is safe to show directly to Church staff; none of them ever
 * mentions "delivery object", internal state machine terms, or
 * Organization-internal detail.
 */
class OrganizationCommunicationResponseException extends RuntimeException
{
    public static function conflictingResponse(): self
    {
        return new self('This communication already has a different response recorded and cannot be changed.');
    }

    public static function withdrawn(): self
    {
        return new self('The Organization has withdrawn this communication, so a new response cannot be recorded.');
    }

    public static function expired(): self
    {
        return new self('The response window for this communication has closed.');
    }

    public static function detached(): self
    {
        return new self('Your Church is no longer connected to this Organization, so a new response cannot be recorded.');
    }

    public static function organizationUnavailable(): self
    {
        return new self('This Organization is not currently active, so a new response cannot be recorded right now.');
    }
}
