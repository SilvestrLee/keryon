<?php

namespace App\Trust\Ai;

use App\Enums\AiDataOrigin;
use App\Enums\Capability;
use App\Enums\DataClassification;
use App\Support\TenantContext;

class AiProcessingPolicy
{
    public function __construct(
        private readonly TenantContext $tenants,
        private readonly AiProviderRegistry $providers,
    ) {}

    public function authorize(AiProcessingRequest $request): void
    {
        $membership = $this->tenants->currentMembership();

        if ($membership === null || $membership->church_id !== $request->churchId) {
            $this->deny('invalid_tenant_context');
        }

        if (! $membership->hasCapability(Capability::FaithflowUse)) {
            $this->deny('capability_denied');
        }

        if ($request->origin === AiDataOrigin::Care || $request->classification === DataClassification::HighlyRestricted) {
            $this->deny('prohibited_data');
        }

        $provider = $this->providers->provider($request->provider);

        if ($provider === null) {
            $this->deny('unknown_provider');
        }

        if (! in_array($provider['status'] ?? null, ['approved', 'restricted'], true)) {
            $this->deny('provider_not_approved');
        }

        foreach ([
            'legal_entity',
            'region',
            'storage_region',
            'retention',
            'safety_retention',
            'training_policy',
            'deletion_mechanism',
            'subprocessor_reference',
            'reviewed_at',
            'evidence_reference',
            'account_configuration_verified_at',
        ] as $fact) {
            if (blank($provider[$fact] ?? null) || ($provider[$fact] ?? null) === 'unknown') {
                $this->deny('provider_governance_unresolved');
            }
        }

        if (($provider['contract_status'] ?? null) !== 'accepted' || ($provider['dpa_status'] ?? null) !== 'accepted') {
            $this->deny('provider_contract_unresolved');
        }

        $capability = $provider['capabilities'][$request->capability->value] ?? null;

        if (! is_array($capability)) {
            $this->deny('capability_not_approved');
        }

        if (! in_array($request->model, $capability['models'] ?? [], true)) {
            $this->deny('model_not_approved');
        }

        if (! in_array($request->classification->value, $capability['data_classifications'] ?? [], true)) {
            $this->deny('classification_not_approved');
        }

        if ($request->containsMedia && ! ($capability['media_allowed'] ?? false)) {
            $this->deny('media_not_approved');
        }
    }

    private function deny(string $reason): never
    {
        throw new AiProcessingDeniedException($reason);
    }
}
