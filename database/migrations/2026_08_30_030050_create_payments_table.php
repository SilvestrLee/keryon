<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('payments_uuid_unique');
            $table->foreignId('billing_account_id')->constrained()->restrictOnDelete();
            $table->string('status');
            $table->string('source');
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->timestamp('received_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('failure_category')->nullable();
            $table->string('idempotency_key')->unique('payments_idempotency_unique');
            $table->string('manual_institution')->nullable();
            $table->string('manual_reference')->nullable();
            $table->timestamps();
            $table->index(['billing_account_id', 'status'], 'payments_account_status_idx');
            $table->unique(['billing_account_id', 'currency', 'manual_institution', 'manual_reference'], 'payments_manual_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
