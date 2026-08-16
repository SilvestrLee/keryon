<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('church_id')
                ->constrained()
                ->cascadeOnDelete();

            // Attribution only — church_id owns the record. A staff
            // account being removed later must not destroy church
            // communication content. See K-CONTENT-002 §2.
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('title');

            $table->string('content_type');

            $table->text('body');

            $table->string('status')->default('draft');

            $table->string('origin')->default('human');

            // Current/most-recent actionable feedback only — not a
            // historical audit trail. Cleared on resubmission or
            // approval. See K-CONTENT-002 §4.
            $table->text('rejection_reason')->nullable();

            $table->timestamp('approved_at')->nullable();

            $table->softDeletes();

            $table->timestamps();

            $table->index(['church_id', 'status']);
            $table->index(['church_id', 'content_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_items');
    }
};
