<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('invoices_uuid_unique');
            $table->foreignId('merchant_legal_entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('billing_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('replacement_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('invoice_number')->nullable();
            $table->string('status')->default('draft');
            $table->char('currency', 3);
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->unsignedBigInteger('amount_paid_minor')->default(0);
            $table->unsignedBigInteger('amount_due_minor')->default(0);
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('issuance_key')->unique('invoices_issuance_key_unique');
            $table->json('billing_profile_snapshot')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();
            $table->unique(['merchant_legal_entity_id', 'invoice_number'], 'invoices_entity_number_unique');
            $table->index(['billing_account_id', 'status', 'issued_at'], 'invoices_account_status_issued_idx');
            $table->index(['currency', 'status'], 'invoices_currency_status_idx');
            $table->index(['period_start', 'period_end'], 'invoices_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
