<?php

namespace App\ChurchStaff;

use App\Enums\ChurchAccessAuditEventType;
use App\Enums\ChurchRole;
use App\Enums\ChurchStaffInvitationStatus;
use App\Enums\MembershipStatus;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\ChurchStaffInvitation;
use App\Models\User;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class InviteChurchStaff
{
    /** @param list<ChurchRole|string> $roles */
    public function execute(ChurchMembership $actor, string $email, array $roles, string $idempotencyKey, ?ChurchStaffInvitation $replaces = null): ChurchStaffInvitationResult
    {
        ChurchStaffAuthorizer::manage($actor, $actor->church_id);
        $email = strtolower(trim($email));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('A valid staff email address is required.');
        }
        if (! Str::isUuid($idempotencyKey)) {
            throw new DomainException('A valid invitation idempotency key is required.');
        }
        $roles = RoleSet::normalize($roles);

        try {
            return DB::transaction(function () use ($actor, $email, $roles, $idempotencyKey, $replaces): ChurchStaffInvitationResult {
                Church::query()->lockForUpdate()->findOrFail($actor->church_id);
                if ($existing = ChurchStaffInvitation::query()->where('idempotency_key', $idempotencyKey)->first()) {
                    if ($existing->church_id !== $actor->church_id) {
                        throw new DomainException('Invitation idempotency belongs to another Church.');
                    }

                    return new ChurchStaffInvitationResult($existing->load('roles'), null, false);
                }
                $pendingIdentity = ChurchStaffInvitation::pendingIdentity($actor->church_id, $email);
                if ($pending = ChurchStaffInvitation::query()->where('pending_identity', $pendingIdentity)->lockForUpdate()->first()) {
                    return new ChurchStaffInvitationResult($pending->load('roles'), null, false);
                }
                $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();
                $membership = $user ? ChurchMembership::query()->where('church_id', $actor->church_id)->where('user_id', $user->id)->lockForUpdate()->first() : null;
                if ($membership?->status === MembershipStatus::ACTIVE) {
                    throw new DomainException('This person is already an active Church staff member.');
                }
                if ($membership?->status === MembershipStatus::SUSPENDED) {
                    throw new DomainException('This staff membership is suspended. Use reactivation instead.');
                }

                if ($replaces !== null) {
                    $old = ChurchStaffInvitation::query()->lockForUpdate()->findOrFail($replaces->id);
                    if ($old->church_id !== $actor->church_id || $old->status !== ChurchStaffInvitationStatus::REVOKED) {
                        throw new DomainException('Replacement must reference a revoked invitation in this Church.');
                    }
                }
                $token = ChurchStaffInvitationTokenService::generate();
                $invitation = ChurchStaffInvitation::query()->create([
                    'uuid' => (string) Str::uuid(), 'church_id' => $actor->church_id,
                    'email_normalized' => $email, 'prospective_user_id' => $user?->id,
                    'status' => ChurchStaffInvitationStatus::PENDING, 'pending_identity' => $pendingIdentity,
                    'token_hash' => ChurchStaffInvitationTokenService::hash($token),
                    'token_expires_at' => now()->addHours(ChurchStaffInvitationTokenService::TTL_HOURS),
                    'sent_at' => now(), 'invited_by_membership_id' => $actor->id,
                    'idempotency_key' => $idempotencyKey, 'replaces_invitation_id' => $replaces?->id,
                ]);
                foreach ($roles as $role) {
                    $invitation->roles()->create(['role' => $role]);
                }
                ChurchAccessAudit::record(ChurchAccessAuditEventType::STAFF_INVITED, $actor->church_id, 'invitation', $invitation->id, $actor, null, ['email' => $email, 'roles' => RoleSet::values($roles)]);

                return new ChurchStaffInvitationResult($invitation->fresh('roles'), $token, true);
            }, 3);
        } catch (QueryException $exception) {
            $pending = ChurchStaffInvitation::query()->where('pending_identity', ChurchStaffInvitation::pendingIdentity($actor->church_id, $email))->first();
            if ($pending) {
                return new ChurchStaffInvitationResult($pending->load('roles'), null, false);
            }
            throw $exception;
        }
    }
}
