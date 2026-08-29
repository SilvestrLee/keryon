<?php

namespace App\Jobs\FaithFlow\Concerns;

use App\Enums\Capability;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;

trait AuthorizesFaithFlowExecution
{
    protected function authorizeFaithFlowExecution(): void
    {
        $membership = app(TenantContext::class)->currentMembership();

        if ($membership === null || ! $membership->hasCapability(Capability::FaithflowUse)) {
            throw new AuthorizationException('The restored membership is not authorized to use FaithFlow.');
        }
    }
}
