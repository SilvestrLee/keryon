<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_webhook_receipts', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 32);
            $table->string('provider_account_key', 96);
            $table->string('provider_event_id', 191);
            $table->string('provider_event_type', 96);
            $table->string('status')->default('received');
            $table->timestamp('received_at');
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->char('payload_hash', 64);
            $table->string('bounded_object_reference')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->string('last_failure_category')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_account_key', 'provider_event_id'], 'webhook_receipts_event_unique');
            $table->index(['status', 'received_at'], 'webhook_receipts_status_received_idx');
            $table->index(['provider', 'provider_event_type'], 'webhook_receipts_provider_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_webhook_receipts');
    }
};
