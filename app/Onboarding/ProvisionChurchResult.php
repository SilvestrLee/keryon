<?php

namespace App\Onboarding;

use App\Models\Church;
use App\Models\ChurchActivation;

final readonly class ProvisionChurchResult
{
    public function __construct(public Church $church, public ChurchActivation $activation, public bool $created) {}
}
