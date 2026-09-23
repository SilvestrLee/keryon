<?php

namespace Tests\Unit\Legal;

use App\Enums\PolicyGovernanceDeliveryStatus;
use App\Enums\PolicyGovernanceTransitionType;
use App\Models\PolicyGovernanceEvent;
use App\Trust\Legal\FakeGovernanceEventDeliveryChannel;
use App\Trust\Legal\PolicyGovernanceEventRelay;
use App\Trust\Legal\UnavailableGovernanceEventDeliveryChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyGovernanceEventRelayTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unavailable_destination_leaves_the_event_pending_and_records_the_attempt(): void
    {
        $event = PolicyGovernanceEvent::record('terms', 'v1', PolicyGovernanceTransitionType::APPROVED, str_repeat('a', 64), 'operator:jane');
        $channel = (new FakeGovernanceEventDeliveryChannel)->markUnavailable($event->event_uuid);
        $relay = new PolicyGovernanceEventRelay($channel);

        $delivered = $relay->relayOne($event);

        $this->assertFalse($delivered);
        $fresh = $event->fresh();
        $this->assertSame(PolicyGovernanceDeliveryStatus::PENDING, $fresh->delivery_status);
        $this->assertSame(1, $fresh->delivery_attempts);
        $this->assertNotNull($fresh->last_attempted_at);
        $this->assertNull($fresh->external_acknowledgement_reference);
        $this->assertNull($fresh->delivered_at);
    }

    public function test_an_empty_string_acknowledgement_is_treated_as_unsuccessful_delivery_without_a_model_exception(): void
    {
        $event = PolicyGovernanceEvent::record('terms', 'v1', PolicyGovernanceTransitionType::APPROVED, str_repeat('a', 64), 'operator:jane');
        $channel = new class implements \App\Trust\Legal\GovernanceEventDeliveryChannel
        {
            public function deliver(PolicyGovernanceEvent $event): ?string
            {
                return '';
            }
        };
        $relay = new PolicyGovernanceEventRelay($channel);

        $delivered = $relay->relayOne($event);

        $this->assertFalse($delivered);
        $fresh = $event->fresh();
        $this->assertSame(PolicyGovernanceDeliveryStatus::PENDING, $fresh->delivery_status);
        $this->assertSame(1, $fresh->delivery_attempts);
        $this->assertNull($fresh->external_acknowledgement_reference);
    }

    public function test_a_whitespace_only_acknowledgement_is_treated_as_unsuccessful_delivery_without_a_model_exception(): void
    {
        $event = PolicyGovernanceEvent::record('terms', 'v1', PolicyGovernanceTransitionType::APPROVED, str_repeat('a', 64), 'operator:jane');
        $channel = new class implements \App\Trust\Legal\GovernanceEventDeliveryChannel
        {
            public function deliver(PolicyGovernanceEvent $event): ?string
            {
                return "   \n\t";
            }
        };
        $relay = new PolicyGovernanceEventRelay($channel);

        $delivered = $relay->relayOne($event);

        $this->assertFalse($delivered);
        $fresh = $event->fresh();
        $this->assertSame(PolicyGovernanceDeliveryStatus::PENDING, $fresh->delivery_status);
        $this->assertNull($fresh->external_acknowledgement_reference);
    }

    public function test_a_channel_that_throws_is_treated_identically_to_unavailable(): void
    {
        $event = PolicyGovernanceEvent::record('terms', 'v1', PolicyGovernanceTransitionType::APPROVED, str_repeat('a', 64), 'operator:jane');
        $channel = new class implements \App\Trust\Legal\GovernanceEventDeliveryChannel
        {
            public function deliver(PolicyGovernanceEvent $event): ?string
            {
                throw new \RuntimeException('destination threw unexpectedly');
            }
        };
        $relay = new PolicyGovernanceEventRelay($channel);

        $delivered = $relay->relayOne($event);

        $this->assertFalse($delivered);
        $fresh = $event->fresh();
        $this->assertSame(PolicyGovernanceDeliveryStatus::PENDING, $fresh->delivery_status);
        $this->assertSame(1, $fresh->delivery_attempts);
    }

    public function test_a_confirmed_delivery_marks_the_event_delivered_with_its_acknowledgement(): void
    {
        $event = PolicyGovernanceEvent::record('terms', 'v1', PolicyGovernanceTransitionType::APPROVED, str_repeat('a', 64), 'operator:jane');
        $channel = new FakeGovernanceEventDeliveryChannel;
        $relay = new PolicyGovernanceEventRelay($channel);

        $delivered = $relay->relayOne($event);

        $this->assertTrue($delivered);
        $fresh = $event->fresh();
        $this->assertSame(PolicyGovernanceDeliveryStatus::DELIVERED, $fresh->delivery_status);
        $this->assertSame('ack-'.$event->event_uuid, $fresh->external_acknowledgement_reference);
        $this->assertNotNull($fresh->delivered_at);
        $this->assertSame(1, $fresh->delivery_attempts);
    }

    public function test_an_already_delivered_event_is_not_reprocessed_and_the_channel_is_not_called_again(): void
    {
        $event = PolicyGovernanceEvent::record('terms', 'v1', PolicyGovernanceTransitionType::APPROVED, str_repeat('a', 64), 'operator:jane');
        $channel = new FakeGovernanceEventDeliveryChannel;
        $relay = new PolicyGovernanceEventRelay($channel);

        $this->assertTrue($relay->relayOne($event));
        $this->assertSame(1, $channel->attemptCountFor($event->event_uuid));

        // A second relay attempt against the same (now-delivered) row must not touch the channel again.
        $second = $relay->relayOne($event->fresh());
        $this->assertFalse($second);
        $this->assertSame(1, $channel->attemptCountFor($event->event_uuid));
        $this->assertSame(1, $event->fresh()->delivery_attempts);
    }

    public function test_retrying_after_a_transient_unavailability_does_not_create_a_duplicate_external_event(): void
    {
        $event = PolicyGovernanceEvent::record('terms', 'v1', PolicyGovernanceTransitionType::APPROVED, str_repeat('a', 64), 'operator:jane');
        $channel = (new FakeGovernanceEventDeliveryChannel)->markUnavailable($event->event_uuid);
        $relay = new PolicyGovernanceEventRelay($channel);

        $this->assertFalse($relay->relayOne($event));
        $this->assertNull($channel->acknowledgementFor($event->event_uuid));

        $channel->markAvailable($event->event_uuid);
        $this->assertTrue($relay->relayOne($event->fresh()));

        $firstAck = $channel->acknowledgementFor($event->event_uuid);
        $this->assertNotNull($firstAck);

        // A hypothetical further retry (e.g. a duplicate relay invocation racing the first) would
        // still resolve to the exact same acknowledgement reference at the destination — the
        // event_uuid is the idempotency key, so a real destination could never mint a second,
        // distinct record for it. Simulated here by calling the channel directly a second time.
        $this->assertSame($firstAck, $channel->deliver($event->fresh()));
        // 3 total calls to the channel: the unavailable attempt, the relay's successful attempt,
        // and this extra direct call simulating a race — all three resolve to the same ack.
        $this->assertSame(3, $channel->attemptCountFor($event->event_uuid));
    }

    public function test_relay_pending_processes_multiple_events_and_reports_a_summary(): void
    {
        $a = PolicyGovernanceEvent::record('terms', 'v1', PolicyGovernanceTransitionType::APPROVED, str_repeat('a', 64), 'operator:jane');
        $b = PolicyGovernanceEvent::record('privacy', 'v1', PolicyGovernanceTransitionType::APPROVED, str_repeat('b', 64), 'operator:jane');
        $channel = (new FakeGovernanceEventDeliveryChannel)->markUnavailable($b->event_uuid);
        $relay = new PolicyGovernanceEventRelay($channel);

        $summary = $relay->relayPending();

        $this->assertSame(['attempted' => 2, 'delivered' => 1], $summary);
        $this->assertSame(PolicyGovernanceDeliveryStatus::DELIVERED, $a->fresh()->delivery_status);
        $this->assertSame(PolicyGovernanceDeliveryStatus::PENDING, $b->fresh()->delivery_status);
    }

    public function test_the_default_bound_channel_is_the_honest_unavailable_implementation(): void
    {
        $this->assertInstanceOf(
            UnavailableGovernanceEventDeliveryChannel::class,
            app(\App\Trust\Legal\GovernanceEventDeliveryChannel::class),
        );

        $event = PolicyGovernanceEvent::record('terms', 'v1', PolicyGovernanceTransitionType::APPROVED, str_repeat('a', 64), 'operator:jane');
        $relay = app(PolicyGovernanceEventRelay::class);

        $this->assertFalse($relay->relayOne($event));
        $this->assertSame(PolicyGovernanceDeliveryStatus::PENDING, $event->fresh()->delivery_status);
    }
}
