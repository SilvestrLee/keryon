<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->timestamp('allocated_at');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('origin');
            $table->string('idempotency_key')->unique('payment_allocations_idempotency_unique');
            $table->timestamps();
            $table->index('payment_id', 'payment_allocations_payment_idx');
            $table->index('invoice_id', 'payment_allocations_invoice_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};
