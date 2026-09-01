<?php

namespace App\Platform;

use App\Enums\PlatformAuditEventType;
use App\Enums\PlatformAuditReasonCategory;
use App\Enums\PlatformAuditTargetType;
use App\Models\PlatformAuditEvent;
use App\Models\PlatformMembership;
use Illuminate\Support\Str;

final class PlatformAudit
{
    private const ALLOWED_STATE_KEYS = [
        PlatformAuditTargetType::PLATFORM_MEMBERSHIP->value => ['user_id', 'role', 'status'],
        PlatformAuditTargetType::CHURCH->value => ['church_id', 'activation_id', 'activation_status', 'created', 'actor_role', 'capability', 'result'],
        PlatformAuditTargetType::CHURCH_ACTIVATION->value => ['status', 'token_generation', 'delivery_attempt_id', 'actor_role', 'capability', 'result'],
        PlatformAuditTargetType::CHURCH_DOMAIN->value => ['status', 'tls_status', 'retry_requested', 'actor_role', 'capability', 'result'],
    ];

    /** @param array<string, scalar|null>|null $previous @param array<string, scalar|null>|null $new */
    public static function record(
        PlatformAuditEventType $event,
        PlatformAuditTargetType $targetType,
        string|int $targetId,
        ?PlatformMembership $actor,
        ?array $previous,
        ?array $new,
        ?PlatformAuditReasonCategory $reason,
        ?string $note = null,
        ?string $correlationId = null,
    ): PlatformAuditEvent {
        self::assertAllowedState($targetType, $previous);
        self::assertAllowedState($targetType, $new);

        return PlatformAuditEvent::query()->create([
            'platform_membership_id' => $actor?->id,
            'actor_user_id' => $actor?->user_id,
            'event_type' => $event,
            'target_type' => $targetType,
            'target_id' => (string) $targetId,
            'previous_state' => $previous,
            'new_state' => $new,
            'reason_category' => $reason,
            'reason_note' => $note === null ? null : Str::limit(trim($note), 500, ''),
            'correlation_id' => $correlationId ?? (string) Str::uuid(),
            'occurred_at' => now(),
        ]);
    }

    /** @param array<string, scalar|null>|null $state */
    private static function assertAllowedState(PlatformAuditTargetType $targetType, ?array $state): void
    {
        if ($state === null) {
            return;
        }

        $unexpected = array_diff(array_keys($state), self::ALLOWED_STATE_KEYS[$targetType->value]);
        if ($unexpected !== []) {
            throw new \DomainException('Platform audit state contains fields that are not allowlisted.');
        }
    }
}
