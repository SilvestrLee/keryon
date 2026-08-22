<?php

namespace App\Design\Rendering\Exceptions;

use RuntimeException;

class DesignRendererException extends RuntimeException
{
    public function __construct(public readonly string $failureCode)
    {
        parent::__construct('The design renderer could not complete the requested output.');
    }
}
