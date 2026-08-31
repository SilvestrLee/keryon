<?php

namespace App\InvitationDelivery;

use App\Enums\InvitationDeliveryStatus;
use App\Enums\InvitationDeliverySubjectType;
use App\Models\InvitationDeliveryAttempt;
use Illuminate\Support\Str;

final class CreateInvitationDeliveryAttempt
{
    public function execute(InvitationDeliverySubjectType $type, int $subjectId, int $churchId, string $recipient, string $tokenHash, InvitationDeliveryMessage $message, bool $rateLimitChecked = false): InvitationDeliveryAttempt
    {
        if (! $rateLimitChecked) {
            app(InvitationDeliveryRateLimiter::class)->ensureSendAllowed($type->value.':'.$subjectId, $churchId, $recipient);
        }
        $fingerprint = TokenGenerationFingerprint::make($tokenHash);

        InvitationDeliveryAttempt::query()
            ->where($type === InvitationDeliverySubjectType::CHURCH_ACTIVATION ? 'church_activation_id' : 'church_staff_invitation_id', $subjectId)
            ->whereIn('status', [InvitationDeliveryStatus::REQUESTED->value, InvitationDeliveryStatus::QUEUED->value])
            ->update(['status' => InvitationDeliveryStatus::SUPERSEDED->value, 'superseded_at' => now(), 'sensitive_payload' => null, 'sensitive_payload_cleared_at' => now()]);

        return InvitationDeliveryAttempt::query()->create([
            'uuid' => (string) Str::uuid(), 'subject_type' => $type,
            'church_activation_id' => $type === InvitationDeliverySubjectType::CHURCH_ACTIVATION ? $subjectId : null,
            'church_staff_invitation_id' => $type === InvitationDeliverySubjectType::CHURCH_STAFF_INVITATION ? $subjectId : null,
            'recipient_email' => strtolower($recipient), 'status' => InvitationDeliveryStatus::REQUESTED,
            'token_fingerprint' => $fingerprint,
            'sensitive_payload' => [
                'recipient' => $message->recipient, 'church_name' => $message->churchName,
                'url' => $message->url, 'expires_at' => $message->expiresAt,
                'roles' => $message->roles, 'tracking_disabled' => true,
            ],
            'requested_at' => now(), 'provider_account_key' => config('invitation-delivery.provider_account_key'),
        ]);
    }
}
