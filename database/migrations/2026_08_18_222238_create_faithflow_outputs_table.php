<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Additive foundation migration — see K-FAITHFLOW-001A §9-14/§21 and
// K-FAITHFLOW-001B §7/§11/§14/§26/§28. One row per selected FaithFlowOutputType
// per run — this is what makes "one source, many outputs" (K-FAITHFLOW-001A
// §27) and per-output partial failure (§26) fall out of the schema for free.
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('faithflow_outputs', function (Blueprint $table) {
            $table->id();

            // Denormalized directly onto this table rather than relied on
            // only through faithflow_run_id — every Keryon tenant-owned
            // model carries its own church_id (see CLAUDE.md Tenancy Rules),
            // exactly like content_items does.
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('faithflow_run_id')->constrained()->cascadeOnDelete();

            $table->string('output_type');
            $table->string('status')->default('pending');

            // Editable working copy vs. the last raw AI output — see
            // K-FAITHFLOW-001A §19/§20 and K-FAITHFLOW-001B §14. Regeneration
            // overwrites generated_content; content is only auto-overwritten
            // when the user hasn't diverged yet (edited_at is null).
            $table->text('content')->nullable();
            $table->text('generated_content')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->unsignedInteger('regeneration_count')->default(0);
            $table->text('error_message')->nullable();

            // One-directional handoff link only — content_items itself is
            // not modified by this migration. Justified in
            // K-FAITHFLOW-001A §21/§26 for traceability from the FaithFlow
            // side; nullable/nullOnDelete because a later ContentItem
            // removal must not delete this row's history.
            $table->foreignId('content_item_id')->nullable()->constrained('content_items')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->softDeletes();
            $table->timestamps();

            // The idempotency foundation — see K-FAITHFLOW-001A §22 and
            // K-FAITHFLOW-001B §36. One live row per output type per run;
            // a duplicate "Generate" click is a no-op against this
            // constraint, not a second row.
            $table->unique(['faithflow_run_id', 'output_type']);
            $table->index(['church_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faithflow_outputs');
    }
};
