<?php

namespace App\Platform\Operations;

use App\Enums\PlatformAuditEventType;
use App\Enums\PlatformAuditReasonCategory;
use App\Enums\PlatformAuditTargetType;
use App\Enums\PlatformCapability;
use App\InvitationDelivery\RequestChurchActivationDelivery;
use App\Models\ChurchActivation;
use App\Onboarding\ChurchActivationTokenService;
use App\Platform\PlatformAudit;
use Illuminate\Support\Facades\DB;

final class PlatformResendActivation extends PlatformOperation
{
    public function execute(int $activationId, PlatformAuditReasonCategory $reason, string $note, string $correlationId): PlatformOperationResult
    {
        $actor = $this->actor(PlatformCapability::PlatformActivationResend);
        $this->requireReason($note);
        $this->requireCorrelation($correlationId);

        return DB::transaction(function () use ($activationId, $reason, $note, $correlationId, $actor): PlatformOperationResult {
            $activation = ChurchActivation::query()->lockForUpdate()->findOrFail($activationId);
            if ($this->prior(PlatformAuditEventType::ACTIVATION_INVITATION_RESENT->value, PlatformAuditTargetType::CHURCH_ACTIVATION->value, $activationId, $correlationId)) {
                return new PlatformOperationResult('This resend request was already completed.', 'church_activation', $activationId, ['status' => $activation->status->value], false);
            }
            $hadToken = $activation->token_expires_at !== null;
            $token = app(ChurchActivationTokenService::class)->issue($activation);
            $attempt = app(RequestChurchActivationDelivery::class)->execute($activation->fresh(), $token);
            PlatformAudit::record(PlatformAuditEventType::ACTIVATION_INVITATION_RESENT, PlatformAuditTargetType::CHURCH_ACTIVATION, $activationId, $actor,
                ['status' => $activation->status->value, 'token_generation' => $hadToken ? 'previous' : 'none', 'delivery_attempt_id' => null],
                ['status' => $activation->status->value, 'token_generation' => 'rotated', 'delivery_attempt_id' => $attempt->id, 'actor_role' => $actor->role->value, 'capability' => PlatformCapability::PlatformActivationResend->value, 'result' => 'queued'], $reason, $note, $correlationId);

            return new PlatformOperationResult('Activation invitation rotated and queued for delivery.', 'church_activation', $activationId, ['status' => $activation->status->value, 'delivery_attempt_id' => $attempt->id], true);
        }, 3);
    }
}
