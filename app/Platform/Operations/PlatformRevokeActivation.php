<?php

namespace App\Platform\Operations;

use App\Enums\PlatformAuditEventType;
use App\Enums\PlatformAuditReasonCategory;
use App\Enums\PlatformAuditTargetType;
use App\Enums\PlatformCapability;
use App\Models\ChurchActivation;
use App\Onboarding\ChurchActivationTokenService;
use App\Platform\PlatformAudit;
use Illuminate\Support\Facades\DB;

final class PlatformRevokeActivation extends PlatformOperation
{
    public function execute(int $activationId, PlatformAuditReasonCategory $reason, string $note, string $correlationId): PlatformOperationResult
    {
        $actor = $this->actor(PlatformCapability::PlatformActivationRevoke);
        $this->requireReason($note);
        $this->requireCorrelation($correlationId);

        return DB::transaction(function () use ($activationId, $reason, $note, $correlationId, $actor): PlatformOperationResult {
            $activation = ChurchActivation::query()->lockForUpdate()->findOrFail($activationId);
            if ($this->prior(PlatformAuditEventType::ACTIVATION_REVOKED->value, PlatformAuditTargetType::CHURCH_ACTIVATION->value, $activationId, $correlationId)) {
                return new PlatformOperationResult('This revocation request was already completed.', 'church_activation', $activationId, ['status' => $activation->status->value], false);
            }$previous = $activation->status->value;
            $revoked = app(ChurchActivationTokenService::class)->revoke($activation);
            PlatformAudit::record(PlatformAuditEventType::ACTIVATION_REVOKED, PlatformAuditTargetType::CHURCH_ACTIVATION, $activationId, $actor, ['status' => $previous, 'token_generation' => $activation->token_expires_at ? 'present' : 'none', 'delivery_attempt_id' => null], ['status' => $revoked->status->value, 'token_generation' => 'revoked', 'delivery_attempt_id' => null, 'actor_role' => $actor->role->value, 'capability' => PlatformCapability::PlatformActivationRevoke->value, 'result' => 'revoked'], $reason, $note, $correlationId);

            return new PlatformOperationResult('Activation revoked.', 'church_activation', $activationId, ['status' => $revoked->status->value], true);
        }, 3);
    }
}
