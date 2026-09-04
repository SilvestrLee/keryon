<?php

namespace App\Communications\OrganizationInbox\Import\Exceptions;

use RuntimeException;

/**
 * K-ORG-COMMS-001E — the factual, church-friendly failure vocabulary for
 * a rejected or impossible import attempt. Every message is safe to show
 * directly to Church staff.
 */
class OrganizationCommunicationImportException extends RuntimeException
{
    public static function notAccepted(): self
    {
        return new self('Only an Accepted communication can be imported.');
    }

    public static function referenceOnly(): self
    {
        return new self('This communication was shared as reference material. You can review it here, but it cannot be imported directly into your Church workspace.');
    }

    public static function notAuthorized(): self
    {
        return new self('You are not authorized to import this communication.');
    }

    /** @param  list<string>  $missingLabels */
    public static function missingDestinationCapability(array $missingLabels): self
    {
        $list = implode(', ', $missingLabels);

        return new self("Importing this communication also requires: {$list}.");
    }

    public static function revisionAlreadyImportedElsewhere(): self
    {
        return new self('Your Church has already imported this exact Organization communication through a different delivery.');
    }
}
