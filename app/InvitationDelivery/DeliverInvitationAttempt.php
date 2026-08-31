<?php

namespace App\InvitationDelivery;

use App\Enums\ChurchActivationStatus;
use App\Enums\ChurchStaffInvitationStatus;
use App\Enums\InvitationDeliveryFailureCategory;
use App\Enums\InvitationDeliveryStatus;
use App\Enums\InvitationDeliverySubjectType;
use App\Models\InvitationDeliveryAttempt;
use DomainException;

final class DeliverInvitationAttempt
{
    public function execute(string $uuid, InvitationDeliverySubjectType $expected): void
    {
        $attempt = InvitationDeliveryAttempt::query()->where('uuid', $uuid)->firstOrFail();
        if ($attempt->subject_type !== $expected || $attempt->status === InvitationDeliveryStatus::PROVIDER_ACCEPTED) {
            return;
        }
        $subject = $expected === InvitationDeliverySubjectType::CHURCH_ACTIVATION ? $attempt->churchActivation : $attempt->churchStaffInvitation;
        $pending = $expected === InvitationDeliverySubjectType::CHURCH_ACTIVATION ? ChurchActivationStatus::PENDING : ChurchStaffInvitationStatus::PENDING;
        if (! $subject || $subject->status !== $pending || $subject->token_expires_at?->isPast() || ! hash_equals($attempt->token_fingerprint, TokenGenerationFingerprint::make((string) $subject->token_hash))) {
            $attempt->forceFill(['status' => InvitationDeliveryStatus::SUPERSEDED, 'failure_category' => InvitationDeliveryFailureCategory::SUPERSEDED, 'superseded_at' => now(), 'sensitive_payload' => null, 'sensitive_payload_cleared_at' => now()])->save();

            return;
        }
        $payload = $attempt->sensitive_payload;
        if (! is_array($payload) || strtolower($payload['recipient'] ?? '') !== strtolower($attempt->recipient_email)) {
            throw new DomainException('Invitation delivery payload is unavailable.');
        }
        $attempt->increment('attempt_count');
        try {
            $receipt = app(InvitationDeliveryTransport::class)->send($expected, new InvitationDeliveryMessage($payload['recipient'], $payload['church_name'], $payload['url'], $payload['expires_at'], $payload['roles'] ?? []));
            $attempt->forceFill(['status' => InvitationDeliveryStatus::PROVIDER_ACCEPTED, 'provider_accepted_at' => now(), 'provider_message_reference' => $receipt->messageReference, 'sensitive_payload' => null, 'sensitive_payload_cleared_at' => now(), 'failure_category' => null])->save();
        } catch (\Throwable $exception) {
            $attempt->forceFill(['status' => InvitationDeliveryStatus::FAILED, 'failed_at' => now(), 'failure_category' => InvitationDeliveryFailureCategory::TRANSIENT_TRANSPORT])->save();
            throw $exception;
        }
    }
}
