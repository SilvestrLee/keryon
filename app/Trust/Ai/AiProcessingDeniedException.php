<?php

namespace App\Trust\Ai;

use RuntimeException;

class AiProcessingDeniedException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('This information cannot be processed by AI for the requested purpose.');
    }
}
