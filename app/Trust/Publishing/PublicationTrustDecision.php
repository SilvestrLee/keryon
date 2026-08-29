<?php

namespace App\Trust\Publishing;

final readonly class PublicationTrustDecision
{
    /** @param array<string, mixed> $evidence */
    public function __construct(public array $evidence) {}
}
