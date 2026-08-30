<?php

namespace App\ChurchStaff;

use App\Enums\ChurchAccessAuditEventType;
use App\Enums\ChurchStaffInvitationStatus;
use App\Enums\MembershipStatus;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\ChurchStaffInvitation;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AcceptChurchStaffInvitation
{
    public function execute(string $rawToken, User $user, string $termsVersion, string $privacyVersion, string $idempotencyKey): AcceptChurchStaffInvitationResult
    {
        if ($rawToken === '' || $termsVersion === '' || $privacyVersion === '' || ! Str::isUuid($idempotencyKey)) {
            throw new DomainException('Invitation token, legal versions, and idempotency are required.');
        }

        return DB::transaction(function () use ($rawToken, $user, $termsVersion, $privacyVersion, $idempotencyKey): AcceptChurchStaffInvitationResult {
            $invitation = ChurchStaffInvitation::query()->where('token_hash', ChurchStaffInvitationTokenService::hash($rawToken))->lockForUpdate()->first();
            if (! $invitation) {
                throw new DomainException('This staff invitation is unavailable.');
            }
            if ($invitation->status === ChurchStaffInvitationStatus::ACCEPTED) {
                if ($invitation->legal_accepted_by_user_id !== $user->id || $invitation->acceptance_idempotency_key !== $idempotencyKey) {
                    throw new DomainException('This staff invitation is unavailable.');
                }
                $membership = ChurchMembership::query()->where('church_id', $invitation->church_id)->where('user_id', $user->id)->firstOrFail();

                return new AcceptChurchStaffInvitationResult($invitation->fresh('roles'), $membership->fresh('roles'), false);
            }
            if ($invitation->status !== ChurchStaffInvitationStatus::PENDING || ! $invitation->token_expires_at || $invitation->token_expires_at->lte(now())) {
                if ($invitation->status === ChurchStaffInvitationStatus::PENDING) {
                    $invitation->forceFill(['status' => ChurchStaffInvitationStatus::EXPIRED, 'pending_identity' => null, 'token_hash' => null])->save();
                }
                throw new DomainException('This staff invitation is unavailable.');
            }
            if (strtolower($user->email) !== $invitation->email_normalized || ($invitation->prospective_user_id !== null && $invitation->prospective_user_id !== $user->id)) {
                throw new DomainException('Sign in with the invited account to accept this invitation.');
            }
            Church::query()->lockForUpdate()->findOrFail($invitation->church_id);
            $roles = $invitation->roles()->get()->pluck('role')->all();
            if ($roles === []) {
                throw new DomainException('This staff invitation has no governed roles.');
            }
            $membership = ChurchMembership::query()->where('church_id', $invitation->church_id)->where('user_id', $user->id)->lockForUpdate()->first();
            if ($membership && ! in_array($membership->status, [MembershipStatus::REMOVED], true)) {
                throw new DomainException('This account already has a Church membership lifecycle that cannot be accepted through this invitation.');
            }
            $acceptedAt = now();
            if (! $membership) {
                $membership = ChurchMembership::query()->create(['church_id' => $invitation->church_id, 'user_id' => $user->id, 'status' => MembershipStatus::ACTIVE, 'is_primary' => false, 'joined_at' => $acceptedAt, 'activated_at' => $acceptedAt]);
            } else {
                $membership->forceFill(['status' => MembershipStatus::ACTIVE, 'is_primary' => false, 'joined_at' => $acceptedAt, 'activated_at' => $acceptedAt, 'suspended_at' => null, 'removed_at' => null])->save();
            }
            $this->syncRoles($membership, $roles);
            $invitation->forceFill([
                'status' => ChurchStaffInvitationStatus::ACCEPTED, 'pending_identity' => null,
                'accepted_at' => $acceptedAt, 'prospective_user_id' => $user->id,
                'acceptance_idempotency_key' => $idempotencyKey, 'terms_version' => $termsVersion,
                'privacy_version' => $privacyVersion, 'legal_accepted_at' => $acceptedAt,
                'legal_accepted_by_user_id' => $user->id,
            ])->save();
            if ($user->church_id === null && $user->activeMemberships()->count() === 1) {
                $user->forceFill(['church_id' => $invitation->church_id])->save();
            }
            ChurchAccessAudit::record(ChurchAccessAuditEventType::STAFF_JOINED, $invitation->church_id, 'membership', $membership->id, null, null, ['status' => 'active', 'roles' => $membership->roleValues(), 'invitation_id' => $invitation->id]);

            return new AcceptChurchStaffInvitationResult($invitation->fresh('roles'), $membership->fresh('roles'), true);
        }, 3);
    }

    private function syncRoles(ChurchMembership $membership, array $roles): void
    {
        $membership->roles()->delete();
        foreach ($roles as $role) {
            $membership->roles()->create(['role' => $role]);
        }
    }
}
