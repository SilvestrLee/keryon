<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('subscriptions_uuid_unique');
            $table->foreignId('church_id')->constrained()->restrictOnDelete();
            $table->foreignId('billing_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('pricing_market_id')->constrained()->restrictOnDelete();
            $table->string('status');
            // Portable one-effective-base invariant: effective rows occupy slot
            // 1; historical rows release it to NULL (multiple NULLs are valid
            // in both SQLite and MySQL unique indexes).
            $table->unsignedTinyInteger('effective_slot')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('trial_started_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['church_id', 'status'], 'subscriptions_church_status_idx');
            $table->unique(['church_id', 'effective_slot'], 'subscriptions_church_effective_unique');
            $table->index(['billing_account_id', 'status'], 'subscriptions_payer_status_idx');
            $table->index(['pricing_market_id', 'status'], 'subscriptions_market_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
