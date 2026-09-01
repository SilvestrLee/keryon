<?php

namespace App\Domain;

use App\Models\ChurchDomain;

final readonly class DomainClaimResult
{
    public function __construct(
        public ChurchDomain $domain,
        public string $verificationToken,
        public string $verificationHostname,
    ) {}
}
