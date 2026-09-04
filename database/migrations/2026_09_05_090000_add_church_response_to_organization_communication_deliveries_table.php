<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * K-ORG-COMMS-001D §11/§47 — the minimal additive Church-response
 * evidence. Deliberately does not touch `state` (the Organization-side
 * availability lifecycle set by 001C/future-withdrawal) — Accepted/
 * Declined/Expired remain *derived* display states computed from these
 * columns plus `available_until`, not separately persisted, per §11's
 * "prefer minimal persistence" and §48's immutability-preservation rule
 * (only these explicit response fields are ever mutated after creation;
 * every identity/distribution/snapshot column stays fully immutable).
 *
 * Explicit short FK name from the start (K-ORG-COMMS-001B §64 lesson).
 *
 * `org_comm_deliveries_church_only_idx` is a deliberate, PERMANENT
 * addition (never dropped in down()) discovered during real-MySQL
 * migration proof: neither of 001C's original indexes on this table
 * (`org_comm_deliveries_distribution_church_unique` and
 * `org_comm_deliveries_org_church_idx`) has `church_id` as a *leading*
 * column, so `church_id`'s own foreign key (`org_comm_deliveries_
 * church_fk`, created in 001C) was silently relying on an implicit,
 * unnamed InnoDB-generated support index. Adding this migration's two
 * new `church_id`-leading composite indexes made InnoDB drop that
 * implicit index as redundant during this migration's own `up()` ALTER —
 * which then made rolling this migration back (dropping both new
 * composite indexes) fail with MySQL error 1553, because doing so would
 * have left `org_comm_deliveries_church_fk` with no supporting index at
 * all. This explicit, named, permanent index is what 001C's FK actually
 * depends on from this point forward — it belongs conceptually to 001C's
 * FK, not to this migration's response columns, so it is intentionally
 * exempt from this migration's own down() — which in turn means `up()`
 * must be safe to run again after a rollback (the index will already
 * exist), hence the existence check below rather than an unconditional
 * `$table->index(...)`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $indexExists = collect(Schema::getIndexes('organization_communication_deliveries'))
            ->contains(fn (array $index): bool => $index['name'] === 'org_comm_deliveries_church_only_idx');

        if (! $indexExists) {
            Schema::table('organization_communication_deliveries', function (Blueprint $table): void {
                $table->index('church_id', 'org_comm_deliveries_church_only_idx');
            });
        }

        Schema::table('organization_communication_deliveries', function (Blueprint $table): void {
            $table->timestamp('accepted_at')->nullable()->after('available_until');
            $table->timestamp('declined_at')->nullable()->after('accepted_at');
            $table->string('decline_reason_code', 32)->nullable()->after('declined_at');
            $table->foreignId('responded_by_church_membership_id')
                ->nullable()
                ->after('decline_reason_code')
                ->constrained('church_memberships', 'id', 'org_comm_deliveries_responder_fk')
                ->nullOnDelete();

            $table->index(['church_id', 'accepted_at'], 'org_comm_deliveries_church_accepted_idx');
            $table->index(['church_id', 'declined_at'], 'org_comm_deliveries_church_declined_idx');
        });
    }

    public function down(): void
    {
        Schema::table('organization_communication_deliveries', function (Blueprint $table): void {
            $table->dropForeign('org_comm_deliveries_responder_fk');
            $table->dropIndex('org_comm_deliveries_church_accepted_idx');
            $table->dropIndex('org_comm_deliveries_church_declined_idx');
            $table->dropColumn(['accepted_at', 'declined_at', 'decline_reason_code', 'responded_by_church_membership_id']);

            // `org_comm_deliveries_church_only_idx` is intentionally NOT
            // dropped here — see the class docblock above.
        });
    }
};
