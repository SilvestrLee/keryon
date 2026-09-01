<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('platform_audit_events_uuid_unique');
            $table->foreignId('platform_membership_id')->nullable()->constrained('platform_memberships')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 80);
            $table->string('target_type', 60);
            $table->string('target_id', 80);
            $table->json('previous_state')->nullable();
            $table->json('new_state')->nullable();
            $table->string('reason_category', 48)->nullable();
            $table->string('reason_note', 500)->nullable();
            $table->uuid('correlation_id');
            $table->timestamp('occurred_at');

            $table->index(['occurred_at'], 'platform_audit_events_time_idx');
            $table->index(['event_type', 'occurred_at'], 'platform_audit_events_type_time_idx');
            $table->index(['target_type', 'target_id', 'occurred_at'], 'platform_audit_events_target_time_idx');
            $table->index(['actor_user_id', 'occurred_at'], 'platform_audit_events_actor_time_idx');
            $table->unique(['event_type', 'target_type', 'target_id', 'correlation_id'], 'platform_audit_events_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_audit_events');
    }
};
