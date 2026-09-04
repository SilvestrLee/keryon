<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * K-ORG-COMMS-001C §18-§21 — one durable, Organization-owned,
 * Church-addressed delivery row per resolved recipient. This table IS
 * the historical recipient snapshot (§21) — no separate
 * "recipient_snapshots" table. Deliberately NOT `BelongsToChurch` (§19):
 * this is Organization-owned distribution evidence addressed to a
 * Church, not a Church-owned tenant record.
 *
 * `UNIQUE(organization_communication_distribution_id, church_id)` (§20)
 * is the database-level backstop for chunked/retried worker writes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_communication_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')
                ->constrained('organizations', 'id', 'org_comm_deliveries_organization_fk')
                ->restrictOnDelete();
            $table->foreignId('organization_communication_distribution_id')
                ->constrained('organization_communication_distributions', 'id', 'org_comm_deliveries_distribution_fk')
                ->cascadeOnDelete();
            $table->foreignId('organization_communication_revision_id')
                ->constrained('organization_communication_revisions', 'id', 'org_comm_deliveries_revision_fk')
                ->restrictOnDelete();
            $table->foreignId('church_id')
                ->constrained('churches', 'id', 'org_comm_deliveries_church_fk')
                ->restrictOnDelete();
            // Recipient snapshot provenance (§18) — which assignment
            // justified inclusion at snapshot time. Nullable + set null on
            // delete: the assignment may later be superseded/removed
            // without destroying this historical delivery row (§73).
            $table->foreignId('church_organization_assignment_id')
                ->nullable()
                ->constrained('church_organization_assignments', 'id', 'org_comm_deliveries_assignment_fk')
                ->nullOnDelete();

            $table->string('state', 24)->default('available');
            $table->timestamp('available_at');
            $table->timestamp('available_until')->nullable();

            $table->timestamps();

            $table->unique(
                ['organization_communication_distribution_id', 'church_id'],
                'org_comm_deliveries_distribution_church_unique',
            );
            $table->index(['organization_id', 'church_id'], 'org_comm_deliveries_org_church_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_communication_deliveries');
    }
};
