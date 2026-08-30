<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_type');
            $table->foreignId('church_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('billing_account_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('previous_state')->nullable();
            $table->json('new_state')->nullable();
            $table->timestamp('occurred_at');

            $table->index(['church_id', 'occurred_at'], 'commercial_audit_church_idx');
            $table->index(['subscription_id', 'occurred_at'], 'commercial_audit_subscription_idx');
            $table->index(['billing_account_id', 'occurred_at'], 'commercial_audit_payer_idx');
            $table->index(['event_type', 'occurred_at'], 'commercial_audit_event_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_audit_events');
    }
};
