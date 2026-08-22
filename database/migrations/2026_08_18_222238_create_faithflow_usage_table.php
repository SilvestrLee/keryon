<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Additive foundation migration — see K-FAITHFLOW-001A §22/§27/§34/§42 and
// K-FAITHFLOW-001B §22. Append-only operational observability: every
// individual provider call (analysis or generation/regeneration attempt),
// metadata only. Deliberately has no column for prompt/response text — see
// K-FAITHFLOW-001B §23/§53 — this is not a billing table and is not the
// controlled content store (faithflow_runs/faithflow_outputs are).
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('faithflow_usage', function (Blueprint $table) {
            $table->id();

            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('faithflow_run_id')->constrained()->cascadeOnDelete();

            // Null for the run-level analysis operation; set for a
            // generation/regeneration attempt tied to one specific output.
            $table->foreignId('faithflow_output_id')->nullable()->constrained('faithflow_outputs')->nullOnDelete();

            $table->string('operation');
            $table->string('provider');
            $table->string('model');
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedInteger('estimated_cost_cents')->nullable();
            $table->string('status');
            $table->string('error_category')->nullable();

            // Append-only — no updated_at, no soft deletes. This is an
            // audit trail, not a user-editable record.
            $table->timestamp('created_at')->nullable();

            $table->index(['church_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faithflow_usage');
    }
};
