<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Additive foundation migration — see K-FAITHFLOW-001A §9/§10 and
// K-FAITHFLOW-001B §7/§8/§10. FaithFlowRun is one source-material
// transformation workspace: the supplied ministry source text plus its
// canonical analysis (populated by K-FAITHFLOW-001C, not this migration).
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('faithflow_runs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('church_id')->constrained()->cascadeOnDelete();

            // Attribution only — church_id owns the record, mirroring
            // content_items' own philosophy. A staff account being removed
            // later must not destroy a church's FaithFlow work.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->text('source_text');
            $table->unsignedInteger('source_char_count');
            $table->string('status')->default('draft');

            // Populated only from K-FAITHFLOW-001C onward. Left null/empty
            // here deliberately — see K-FAITHFLOW-001A §11 and
            // K-FAITHFLOW-001B §7/§23.
            $table->json('canonical_analysis')->nullable();
            $table->text('analysis_error')->nullable();
            $table->unsignedInteger('analysis_attempts')->default(0);
            $table->string('prompt_version')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['church_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faithflow_runs');
    }
};
