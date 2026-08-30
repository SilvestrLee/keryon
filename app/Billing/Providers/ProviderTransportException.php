<?php

namespace App\Billing\Providers;

use RuntimeException;

class ProviderTransportException extends RuntimeException
{
    public function __construct(public readonly string $category, string $message = 'Payment provider request failed.')
    {
        parent::__construct($message);
    }
}
