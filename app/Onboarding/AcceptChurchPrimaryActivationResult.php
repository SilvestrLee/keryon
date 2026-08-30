<?php

namespace App\Onboarding;

use App\Models\BillingAccount;
use App\Models\Church;
use App\Models\ChurchActivation;
use App\Models\ChurchMembership;
use App\Models\Subscription;

final readonly class AcceptChurchPrimaryActivationResult
{
    public function __construct(
        public Church $church,
        public ChurchActivation $activation,
        public ChurchMembership $membership,
        public BillingAccount $billingAccount,
        public Subscription $subscription,
        public bool $acceptedNow,
    ) {}
}
