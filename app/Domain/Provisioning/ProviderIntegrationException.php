<?php

namespace App\Domain\Provisioning;

use RuntimeException;
use Throwable;

/**
 * Thrown by a DomainProvisioner adapter when an operation with no safe
 * status-enum outcome (currently: deactivate()) cannot be completed —
 * ambiguous provider state or an unrecoverable transport/provider failure.
 *
 * Never include configuration values (especially API tokens) in the
 * exception message — see docs/06-Engineering/Logging_Standard.md.
 */
final class ProviderIntegrationException extends RuntimeException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, previous: $previous);
    }
}
