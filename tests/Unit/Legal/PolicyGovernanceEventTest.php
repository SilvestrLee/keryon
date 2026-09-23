<?php

namespace Tests\Unit\Legal;

use App\Enums\PolicyGovernanceDeliveryStatus;
use App\Enums\PolicyGovernanceTransitionType;
use App\Models\PolicyGovernanceEvent;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyGovernanceEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_creates_a_pending_event_with_all_required_fields(): void
    {
        $event = PolicyGovernanceEvent::record('terms', 'terms-2026-10-01-v1', PolicyGovernanceTransitionType::APPROVED, str_repeat('a', 64), 'platform-operator:jane');

        $this->assertNotEmpty($event->event_uuid);
        $this->assertSame('terms', $event->document_type);
        $this->assertSame('terms-2026-10-01-v1', $event->version);
        $this->assertSame(PolicyGovernanceTransitionType::APPROVED, $event->transition_type);
        $this->assertSame(str_repeat('a', 64), $event->content_hash);
        $this->assertSame('platform-operator:jane', $event->actor_reference);
        $this->assertNotNull($event->occurred_at);
        $this->assertSame(PolicyGovernanceDeliveryStatus::PENDING, $event->delivery_status);
        $this->assertSame(0, $event->delivery_attempts);
        $this->assertNull($event->external_acknowledgement_reference);
        $this->assertDatabaseHas('policy_governance_events', ['event_uuid' => $event->event_uuid, 'delivery_status' => 'pending']);
    }

    public function test_record_rejects_a_content_hash_that_is_not_a_64_character_digest(): void
    {
        $this->expectException(DomainException::class);

        PolicyGovernanceEvent::record('terms', 'v1', PolicyGovernanceTransitionType::APPROVED, 'too-short', 'operator:jane');
    }

    public function test_record_rejects_blank_document_type_version_or_actor_reference(): void
    {
        foreach ([['', 'v1', 'operator:jane'], ['terms', '', 'operator:jane'], ['terms', 'v1', '']] as [$type, $version, $actor]) {
            try {
                PolicyGovernanceEvent::record($type, $version, PolicyGovernanceTransitionType::APPROVED, str_repeat('a', 64), $actor);
                $this->fail('Expected a DomainException for blank required field.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertDatabaseCount('policy_governance_events', 0);
    }

    public function test_identity_and_content_fields_are_immutable_once_recorded(): void
    {
        $event = PolicyGovernanceEvent::record('terms', 'v1', PolicyGovernanceTransitionType::APPROVED, str_repeat('a', 64), 'operator:jane');

        foreach ([
            'document_type' => 'privacy',
            'version' => 'v2',
            'transition_type' => PolicyGovernanceTransitionType::RETIRED->value,
            'content_hash' => str_repeat('b', 64),
            'actor_reference' => 'operator:someone-else',
            'event_uuid' => 'attacker-supplied-uuid',
        ] as $field => $value) {
            $fresh = PolicyGovernanceEvent::find($event->id);
            $fresh->{$field} = $value;
            try {
                $fresh->save();
                $this->fail("Expected mutation of [{$field}] to be rejected.");
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }

        $reloaded = $event->fresh();
        $this->assertSame('terms', $reloaded->document_type);
        $this->assertSame('v1', $reloaded->version);
    }

    public function test_delivery_status_may_only_move_from_pending_to_delivered(): void
    {
        $event = PolicyGovernanceEvent::record('terms', 'v1', PolicyGovernanceTransitionType::APPROVED, str_repeat('a', 64), 'operator:jane');

        // Valid: pending -> delivered, with both acknowledgement fields set in the same write.
        $event->forceFill([
            'delivery_status' => PolicyGovernanceDeliveryStatus::DELIVERED->value,
            'external_acknowledgement_reference' => 'ack-123',
            'delivered_at' => now(),
        ])->save();
        $this->assertSame(PolicyGovernanceDeliveryStatus::DELIVERED, $event->fresh()->delivery_status);

        // Invalid: attempting to move it back to pending.
        $delivered = $event->fresh();
        $delivered->delivery_status = PolicyGovernanceDeliveryStatus::PENDING->value;
        $this->expectException(DomainException::class);
        $delivered->save();
    }

    public function test_marking_delivered_without_an_acknowledgement_reference_is_rejected(): void
    {
        $event = PolicyGovernanceEvent::record('terms', 'v1', PolicyGovernanceTransitionType::APPROVED, str_repeat('a', 64), 'operator:jane');

        $event->delivery_status = PolicyGovernanceDeliveryStatus::DELIVERED->value;
        $event->delivered_at = now();
        // external_acknowledgement_reference intentionally left null.

        $this->expectException(DomainException::class);
        $event->save();
    }

    public function test_acknowledgement_fields_cannot_change_independently_of_a_delivery_transition(): void
    {
        $event = PolicyGovernanceEvent::record('terms', 'v1', PolicyGovernanceTransitionType::APPROVED, str_repeat('a', 64), 'operator:jane');

        $event->external_acknowledgement_reference = 'forged-ack';
        // delivery_status intentionally left at 'pending'.

        $this->expectException(DomainException::class);
        $event->save();
    }

    public function test_delivery_attempts_and_last_attempted_at_remain_freely_mutable(): void
    {
        $event = PolicyGovernanceEvent::record('terms', 'v1', PolicyGovernanceTransitionType::APPROVED, str_repeat('a', 64), 'operator:jane');

        $event->forceFill(['delivery_attempts' => 3, 'last_attempted_at' => now()])->save();

        $this->assertSame(3, $event->fresh()->delivery_attempts);
    }

    public function test_an_event_can_never_be_deleted(): void
    {
        $event = PolicyGovernanceEvent::record('terms', 'v1', PolicyGovernanceTransitionType::APPROVED, str_repeat('a', 64), 'operator:jane');

        $this->expectException(DomainException::class);
        $event->delete();
    }

    public function test_a_delivered_event_can_still_never_be_deleted(): void
    {
        $event = PolicyGovernanceEvent::record('terms', 'v1', PolicyGovernanceTransitionType::APPROVED, str_repeat('a', 64), 'operator:jane');
        $event->forceFill([
            'delivery_status' => PolicyGovernanceDeliveryStatus::DELIVERED->value,
            'external_acknowledgement_reference' => 'ack-1',
            'delivered_at' => now(),
        ])->save();

        $this->expectException(DomainException::class);
        $event->delete();
    }

    public function test_content_body_is_never_a_column_on_this_table(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('policy_governance_events', 'content'));
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('policy_governance_events', 'body'));
    }
}
