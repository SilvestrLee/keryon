<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * K-ORG-COMMS-001C §9/§66-§68 — the single durable distribution
 * aggregate. `target_mode`/`target_unit_id`/`target_church_ids` capture
 * only what the sender deliberately chose (§8); the authoritative
 * historical audience lives on `organization_communication_deliveries`
 * (one row per resolved recipient), not here — see §21/§22.
 *
 * All foreign key and unique constraint names are explicit and short
 * (K-ORG-COMMS-001B §64 — MySQL's 64-char identifier limit already bit
 * the 001A migrations once; not repeating that here).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_communication_distributions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')
                ->constrained('organizations', 'id', 'org_comm_dists_organization_fk')
                ->restrictOnDelete();
            $table->foreignId('organization_communication_id')
                ->constrained('organization_communications', 'id', 'org_comm_dists_communication_fk')
                ->restrictOnDelete();
            $table->foreignId('organization_communication_revision_id')
                ->constrained('organization_communication_revisions', 'id', 'org_comm_dists_revision_fk')
                ->restrictOnDelete();
            $table->foreignId('governing_unit_id')
                ->constrained('organization_units', 'id', 'org_comm_dists_governing_unit_fk')
                ->restrictOnDelete();
            $table->foreignId('initiated_by_organization_membership_id')
                ->constrained('organization_memberships', 'id', 'org_comm_dists_initiator_fk')
                ->restrictOnDelete();

            $table->string('state', 24)->default('pending');

            // What the sender deliberately selected (§8/§67) — not the
            // historical audience.
            $table->string('target_mode', 32);
            $table->foreignId('target_unit_id')
                ->nullable()
                ->constrained('organization_units', 'id', 'org_comm_dists_target_unit_fk')
                ->restrictOnDelete();
            $table->json('target_church_ids')->nullable();
            $table->char('target_definition_hash', 64);

            // §16 locked decision: snapshot_at is set by the worker at the
            // start of the materialization transaction, never at request
            // time.
            $table->timestamp('requested_at');
            $table->timestamp('snapshot_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->unsignedInteger('resolved_recipient_count')->nullable();
            $table->unsignedInteger('delivered_count')->default(0);
            $table->string('failure_reason', 255)->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'state'], 'org_comm_dists_org_state_idx');
            $table->index(['organization_communication_revision_id', 'state'], 'org_comm_dists_revision_state_idx');
            // Idempotency lookup: "is there already a live distribution for
            // this exact revision + target?" (§26, §49).
            $table->index(['organization_communication_revision_id', 'target_definition_hash', 'state'], 'org_comm_dists_dedup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_communication_distributions');
    }
};
