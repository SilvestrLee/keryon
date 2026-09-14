<?php

namespace App\Domain;

use App\Enums\ChurchDomainEventType;
use App\Enums\DomainStatus;
use App\Models\ChurchDomain;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class RegenerateChurchDomainToken
{
    public function __construct(
        private DomainMutationAuthorizer $authorizer,
        private CustomDomainEntitlementGuard $entitlementGuard,
        private ChurchDomainLifecycle $lifecycle,
    ) {}

    public function execute(ChurchDomain $domain): DomainClaimResult
    {
        $membership = $this->authorizer->authorize($domain);
        $this->entitlementGuard->assertAllowed($domain);
        $token = Str::random(64);

        $domain = DB::transaction(function () use ($domain, $membership, $token): ChurchDomain {
            $locked = ChurchDomain::withoutGlobalScope('church_tenant')->lockForUpdate()->findOrFail($domain->getKey());
            if (in_array($locked->status, [DomainStatus::Active, DomainStatus::Disabled, DomainStatus::Released], true)) {
                throw ValidationException::withMessages(['domain' => 'The verification token cannot be regenerated in this domain state.']);
            }
            $locked->forceFill([
                'verification_token_hash' => hash('sha256', $token),
                'ownership_verified_at' => null,
                'status' => DomainStatus::PendingVerification,
                'failure_code' => null,
                'consecutive_failures' => 0,
            ])->save();
            $this->lifecycle->record($locked, ChurchDomainEventType::TokenRegenerated, $membership);

            return $locked;
        }, 3);

        return new DomainClaimResult($domain, $token, '_keryon-verification.'.$domain->normalized_hostname);
    }
}
