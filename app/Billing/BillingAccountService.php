<?php

namespace App\Billing;

use App\Enums\BillingAccountOwnerType;
use App\Enums\BillingAccountStatus;
use App\Enums\CommercialAuditEventType;
use App\Enums\SubscriptionStatus;
use App\Models\BillingAccount;
use App\Models\Church;
use App\Models\CommercialAuditEvent;
use App\Models\Organization;
use App\Models\OrganizationUnit;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class BillingAccountService
{
    public function create(string $name, Model $owner, ?int $actorUserId, string $origin): BillingAccount
    {
        if ($origin === '') {
            throw new DomainException('Billing provisioning requires an explicit origin.');
        }

        return DB::transaction(function () use ($name, $owner, $actorUserId, $origin): BillingAccount {
            [$type, $ownerField] = match (true) {
                $owner instanceof Church => [BillingAccountOwnerType::CHURCH, 'church_id'],
                $owner instanceof Organization => [BillingAccountOwnerType::ORGANIZATION, 'organization_id'],
                $owner instanceof OrganizationUnit => [BillingAccountOwnerType::ORGANIZATION_UNIT, 'organization_unit_id'],
                default => throw new DomainException('Unsupported BillingAccount owner type.'),
            };
            $lockedOwner = $owner::query()->lockForUpdate()->findOrFail($owner->getKey());
            $account = BillingAccount::query()->create([
                'name' => $name, 'owner_type' => $type, $ownerField => $lockedOwner->getKey(),
                'status' => BillingAccountStatus::ACTIVE,
            ]);
            $this->audit(CommercialAuditEventType::BILLING_ACCOUNT_CREATED, $account, null, $actorUserId, null, [
                'owner_type' => $type->value, 'owner_id' => $lockedOwner->getKey(), 'origin' => $origin,
            ]);

            return $account->fresh();
        });
    }

    public function deactivate(BillingAccount $account, ?int $actorUserId, string $origin): BillingAccount
    {
        if ($origin === '') {
            throw new DomainException('Billing lifecycle actions require an explicit origin.');
        }

        return DB::transaction(function () use ($account, $actorUserId, $origin): BillingAccount {
            $locked = BillingAccount::query()->lockForUpdate()->findOrFail($account->id);
            if ($locked->subscriptions()->whereIn('status', [SubscriptionStatus::TRIALING->value, SubscriptionStatus::ACTIVE->value])->exists()) {
                throw new DomainException('A BillingAccount with an effective Subscription cannot be deactivated.');
            }
            $locked->forceFill(['status' => BillingAccountStatus::INACTIVE])->save();
            $this->audit(CommercialAuditEventType::BILLING_ACCOUNT_DEACTIVATED, $locked, null, $actorUserId, ['status' => 'active'], ['status' => 'inactive', 'origin' => $origin]);

            return $locked->fresh();
        });
    }

    private function audit(CommercialAuditEventType $type, BillingAccount $account, ?int $subscriptionId, ?int $actor, ?array $previous, ?array $new): void
    {
        CommercialAuditEvent::query()->create([
            'event_type' => $type,
            'church_id' => $account->church_id,
            'billing_account_id' => $account->id,
            'subscription_id' => $subscriptionId,
            'actor_user_id' => $actor,
            'previous_state' => $previous,
            'new_state' => $new,
            'occurred_at' => now(),
        ]);
    }
}
