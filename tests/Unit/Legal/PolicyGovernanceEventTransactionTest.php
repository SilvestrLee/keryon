<?php

namespace Tests\Unit\Legal;

use App\Enums\PolicyGovernanceTransitionType;
use App\Models\PolicyGovernanceEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Proves the core transactional-outbox guarantee named in
 * K-LEGAL-001B-A-DECISION-ADDENDUM.md §1: writing a PolicyGovernanceEvent is
 * a plain local database write, so it participates fully in whatever
 * surrounding transaction a future PolicyVersion lifecycle transition opens
 * — if that surrounding transaction rolls back, the event never existed as
 * far as any other observer is concerned. This is tested in isolation, since
 * PolicyVersion itself is not built by this milestone.
 */
class PolicyGovernanceEventTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_rolled_back_transaction_leaves_no_committed_governance_event(): void
    {
        try {
            DB::transaction(function (): void {
                PolicyGovernanceEvent::record('terms', 'v1', PolicyGovernanceTransitionType::APPROVED, str_repeat('a', 64), 'operator:jane');
                throw new RuntimeException('Simulating a failure elsewhere in the same business transaction.');
            });
            $this->fail('Expected the simulated failure to propagate.');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertDatabaseCount('policy_governance_events', 0);
    }

    public function test_a_committed_transaction_persists_the_event(): void
    {
        DB::transaction(function (): void {
            PolicyGovernanceEvent::record('terms', 'v1', PolicyGovernanceTransitionType::APPROVED, str_repeat('a', 64), 'operator:jane');
        });

        $this->assertDatabaseCount('policy_governance_events', 1);
    }

    public function test_a_rollback_after_multiple_events_in_the_same_transaction_leaves_none_committed(): void
    {
        try {
            DB::transaction(function (): void {
                PolicyGovernanceEvent::record('terms', 'v1', PolicyGovernanceTransitionType::APPROVED, str_repeat('a', 64), 'operator:jane');
                PolicyGovernanceEvent::record('privacy', 'v1', PolicyGovernanceTransitionType::APPROVED, str_repeat('b', 64), 'operator:jane');
                throw new RuntimeException('Simulating a failure after both writes.');
            });
            $this->fail('Expected the simulated failure to propagate.');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertDatabaseCount('policy_governance_events', 0);
    }

    public function test_nested_transaction_savepoint_rollback_does_not_affect_the_outer_transaction(): void
    {
        // Mirrors how a future controller's outer transaction will wrap a
        // domain action's own DB::transaction(..., 3) call as a savepoint
        // (K-LEGAL-001B-A-ARCHITECTURE.md §8.1) — this proves Laravel's
        // savepoint nesting behaves as that design depends on, using this
        // milestone's own isolated write as the guinea pig.
        DB::transaction(function (): void {
            PolicyGovernanceEvent::record('terms', 'v1', PolicyGovernanceTransitionType::APPROVED, str_repeat('a', 64), 'operator:jane');

            try {
                DB::transaction(function (): void {
                    PolicyGovernanceEvent::record('privacy', 'v1', PolicyGovernanceTransitionType::APPROVED, str_repeat('b', 64), 'operator:jane');
                    throw new RuntimeException('Simulating a failure inside the nested savepoint only.');
                });
            } catch (RuntimeException) {
                // expected — only the inner savepoint rolls back
            }
        });

        // The outer event survives; the inner (rolled-back-at-savepoint-level) one does not.
        $this->assertDatabaseCount('policy_governance_events', 1);
        $this->assertDatabaseHas('policy_governance_events', ['document_type' => 'terms']);
        $this->assertDatabaseMissing('policy_governance_events', ['document_type' => 'privacy']);
    }
}
