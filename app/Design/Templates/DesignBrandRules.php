<?php

namespace App\Design\Templates;

final readonly class DesignBrandRules
{
    public function __construct(
        public bool $logoRequired,
        public bool $markSupported,
        public bool $primaryColorSupported,
        public bool $accentColorSupported,
        public string $logoTreatment = 'auto',
    ) {}
}
