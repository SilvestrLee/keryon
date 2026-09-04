<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * K-ORG-COMMS-001E §30/§55/§66 — the durable Church-side import EVENT.
 * Exactly one row per Accepted delivery that has been imported (§36 —
 * "one successful import event per delivery"), enforced by the unique
 * constraint on `organization_communication_delivery_id`. This is the
 * bounded evidence a future K-ORG-COMMS-001F reads to answer "has this
 * Church imported?" without ever touching local Church content (§56/§97)
 * — `organization_id` is carried directly so that query never needs to
 * join through to local records either.
 *
 * Deliberately belongs to the receiving Church (`BelongsToChurch`-style
 * `church_id`, though the trait itself is not used here since this row
 * is written by an explicit service, not general Eloquent mass
 * assignment) — it is evidence of a Church action, and grants the
 * Organization no mutation authority over it (§31).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_communication_imports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')
                ->constrained('organizations', 'id', 'org_comm_imports_organization_fk')
                ->restrictOnDelete();
            $table->foreignId('church_id')
                ->constrained('churches', 'id', 'org_comm_imports_church_fk')
                ->restrictOnDelete();
            $table->foreignId('organization_communication_delivery_id')
                ->constrained('organization_communication_deliveries', 'id', 'org_comm_imports_delivery_fk')
                ->restrictOnDelete();
            $table->foreignId('organization_communication_revision_id')
                ->constrained('organization_communication_revisions', 'id', 'org_comm_imports_revision_fk')
                ->restrictOnDelete();
            $table->foreignId('imported_by_church_membership_id')
                ->nullable()
                ->constrained('church_memberships', 'id', 'org_comm_imports_importer_fk')
                ->nullOnDelete();
            $table->timestamp('imported_at');
            $table->timestamps();

            // K-ORG-COMMS-001E §36 — the delivery-level MVP import
            // uniqueness boundary, with an explicit short name (§67 —
            // Laravel's auto-generated name for this column exceeds
            // MySQL's 64-char identifier limit).
            $table->unique('organization_communication_delivery_id', 'org_comm_imports_delivery_unique');

            // K-ORG-COMMS-001E §54/§82 — the service-layer duplicate-
            // revision guard (denying a second import of the same
            // revision into the same Church through a different
            // delivery) queries this index; it is not itself a unique
            // constraint, since the delivery-level unique above is the
            // authoritative MVP boundary (§36) and revision reuse across
            // legitimate re-distributions is a Product Office decision
            // (see the K-ORG-COMMS-001E report, decision A).
            $table->index(['church_id', 'organization_communication_revision_id'], 'org_comm_imports_church_revision_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_communication_imports');
    }
};
