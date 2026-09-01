<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('church_domain_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->restrictOnDelete();
            $table->foreignId('church_domain_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('actor_church_membership_id')->nullable()->constrained('church_memberships')->nullOnDelete();
            $table->string('event_type', 40);
            $table->string('failure_code', 40)->nullable();
            $table->uuid('correlation_id');
            $table->timestamp('occurred_at');

            $table->index(['church_domain_id', 'occurred_at'], 'church_domain_events_domain_time_idx');
            $table->index(['church_id', 'event_type', 'occurred_at'], 'church_domain_events_church_type_time_idx');
            $table->unique(['church_domain_id', 'event_type', 'correlation_id'], 'church_domain_events_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('church_domain_events');
    }
};
