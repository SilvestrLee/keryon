<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Every active church must have exactly one active Primary Administrator
 * at all times. See Keryon Blueprint v1.4.1 §7.
 */
class CannotRemoveFinalPrimaryAdministratorException extends RuntimeException
{
    public function __construct(int $churchId)
    {
        parent::__construct(
            "Cannot remove or suspend the final active Primary Administrator for church {$churchId}. Transfer Primary Administrator status to another active membership first."
        );
    }
}
