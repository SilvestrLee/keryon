<?php

namespace App\InvitationDelivery;

use App\ChurchStaff\ChurchStaffInvitationTokenService;
use App\Enums\ChurchStaffInvitationStatus;
use App\Enums\InvitationDeliveryStatus;
use App\Enums\InvitationDeliverySubjectType;
use App\Jobs\DeliverChurchStaffInvitation;
use App\Models\ChurchStaffInvitation;
use App\Models\InvitationDeliveryAttempt;
use DomainException;

final class RequestChurchStaffInvitationDelivery
{
    public function execute(ChurchStaffInvitation $invitation, string $rawToken, bool $rateLimitChecked = false): InvitationDeliveryAttempt
    {
        $invitation->loadMissing(['church', 'roles']);
        if ($invitation->status !== ChurchStaffInvitationStatus::PENDING || $invitation->token_expires_at?->isPast() || ! hash_equals((string) $invitation->token_hash, ChurchStaffInvitationTokenService::hash($rawToken))) {
            throw new DomainException('The staff invitation is not available for delivery.');
        }
        $roles = $invitation->roles->map(fn ($record): string => $record->role->label())->values()->all();
        $message = new InvitationDeliveryMessage($invitation->email_normalized, $invitation->church->name, route('invitations.landing', ['token' => $rawToken, 'type' => 'staff']), $invitation->token_expires_at->toIso8601String(), $roles);
        $attempt = app(CreateInvitationDeliveryAttempt::class)->execute(InvitationDeliverySubjectType::CHURCH_STAFF_INVITATION, $invitation->id, $invitation->church_id, $message->recipient, $invitation->token_hash, $message, $rateLimitChecked);
        DeliverChurchStaffInvitation::dispatch($attempt->uuid)->afterCommit();
        $attempt->forceFill(['status' => InvitationDeliveryStatus::QUEUED, 'queued_at' => now()])->save();

        return $attempt;
    }
}
