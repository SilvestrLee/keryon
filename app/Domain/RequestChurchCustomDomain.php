<?php

namespace App\Domain;

use App\Enums\ChurchDomainEventType;
use App\Enums\DomainStatus;
use App\Enums\DomainTlsStatus;
use App\Enums\DomainVerificationMethod;
use App\Models\Church;
use App\Models\ChurchDomain;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class RequestChurchCustomDomain
{
    public function __construct(
        private DomainNameNormalizer $normalizer,
        private DomainMutationAuthorizer $authorizer,
        private CustomDomainEntitlementGuard $entitlementGuard,
        private ChurchDomainLifecycle $lifecycle,
    ) {}

    public function execute(Church $church, string $hostname): DomainClaimResult
    {
        $membership = $this->authorizer->authorize($church);
        // Entitlement denial must create no ChurchDomain row and generate
        // no verification token — checked before either happens (§27).
        $this->entitlementGuard->assertAllowed($church);

        try {
            $normalized = $this->normalizer->normalize($hostname);
        } catch (\DomainException $exception) {
            throw ValidationException::withMessages(['hostname' => $exception->getMessage()]);
        }

        $token = Str::random(64);

        try {
            $domain = DB::transaction(function () use ($church, $membership, $hostname, $normalized, $token): ChurchDomain {
                Church::query()->lockForUpdate()->findOrFail($church->getKey());

                $activeClaims = ChurchDomain::withoutGlobalScope('church_tenant')
                    ->where('church_id', $church->getKey())
                    ->whereNull('released_at')
                    ->lockForUpdate()
                    ->count();

                if ($activeClaims >= (int) config('public-website.custom_domains.claim_limit', 2)) {
                    throw ValidationException::withMessages(['hostname' => 'A Church may have one primary custom domain and one companion alias.']);
                }

                $domain = ChurchDomain::withoutGlobalScope('church_tenant')->create([
                    'church_id' => $church->getKey(),
                    'normalized_hostname' => $normalized,
                    'display_hostname' => trim($hostname),
                    'status' => DomainStatus::PendingVerification,
                    'verification_method' => DomainVerificationMethod::Txt,
                    'verification_token_hash' => hash('sha256', $token),
                    'tls_status' => DomainTlsStatus::NotStarted,
                    'created_by_church_membership_id' => $membership->getKey(),
                ]);

                $this->lifecycle->record($domain, ChurchDomainEventType::Requested, $membership);

                return $domain;
            }, 3);
        } catch (QueryException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'unique')) {
                // K-DOMAIN-001F §24 — truthful, non-disclosing copy. Never
                // reveal whether the hostname is currently active elsewhere
                // or was previously released (that would leak another
                // Church's history); never imply a timer or reclaim path.
                throw ValidationException::withMessages(['hostname' => 'This domain is already connected or was previously released in Keryon and cannot currently be claimed through self-service.']);
            }
            throw $exception;
        }

        return new DomainClaimResult($domain, $token, '_keryon-verification.'.$normalized);
    }
}
