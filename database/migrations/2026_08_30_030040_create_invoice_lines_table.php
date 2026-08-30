<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('church_id')->constrained()->restrictOnDelete();
            $table->foreignId('subscription_id')->constrained()->restrictOnDelete();
            $table->foreignId('subscription_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('plan_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('price_id')->constrained()->restrictOnDelete();
            $table->foreignId('pricing_market_id')->constrained()->restrictOnDelete();
            $table->string('billing_interval');
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->char('currency', 3);
            $table->unsignedBigInteger('unit_amount_minor');
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
            $table->string('idempotency_key')->unique('invoice_lines_idempotency_unique');
            $table->timestamps();
            $table->index('invoice_id', 'invoice_lines_invoice_idx');
            $table->index('church_id', 'invoice_lines_church_idx');
            $table->index(['subscription_id', 'period_start'], 'invoice_lines_subscription_period_idx');
            $table->index('price_id', 'invoice_lines_price_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
    }
};
