<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('event_type');
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('church_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('organization_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->json('previous_state')->nullable();
            $table->json('new_state')->nullable();
            $table->timestamp('occurred_at');

            $table->index(['organization_id', 'occurred_at']);
            $table->index(['organization_id', 'event_type', 'occurred_at']);
            $table->index(['subject_type', 'subject_id', 'occurred_at']);
            $table->index(['church_id', 'occurred_at']);
            $table->index(['organization_unit_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_audit_events');
    }
};
