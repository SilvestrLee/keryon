<?php

namespace App\InvitationDelivery;

use App\Enums\ChurchActivationStatus;
use App\Enums\InvitationDeliveryStatus;
use App\Enums\InvitationDeliverySubjectType;
use App\Jobs\DeliverChurchActivationInvitation;
use App\Models\ChurchActivation;
use App\Models\InvitationDeliveryAttempt;
use App\Onboarding\ChurchActivationTokenService;
use DomainException;

final class RequestChurchActivationDelivery
{
    public function execute(ChurchActivation $activation, string $rawToken): InvitationDeliveryAttempt
    {
        $activation->loadMissing('church');
        if ($activation->status !== ChurchActivationStatus::PENDING || $activation->token_expires_at?->isPast() || ! hash_equals((string) $activation->token_hash, ChurchActivationTokenService::hash($rawToken))) {
            throw new DomainException('The activation is not available for delivery.');
        }
        $message = new InvitationDeliveryMessage($activation->prospective_primary_email, $activation->church->name, route('invitations.landing', ['token' => $rawToken, 'type' => 'activation']), $activation->token_expires_at->toIso8601String());
        $attempt = app(CreateInvitationDeliveryAttempt::class)->execute(InvitationDeliverySubjectType::CHURCH_ACTIVATION, $activation->id, $activation->church_id, $message->recipient, $activation->token_hash, $message);
        DeliverChurchActivationInvitation::dispatch($attempt->uuid)->afterCommit();
        $attempt->forceFill(['status' => InvitationDeliveryStatus::QUEUED, 'queued_at' => now()])->save();

        return $attempt;
    }
}
