<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_intents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('checkout_intents_uuid_unique');
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('billing_account_id')->constrained()->restrictOnDelete();
            $table->string('provider', 32);
            $table->string('provider_account_key', 96);
            $table->string('provider_environment', 16);
            $table->string('local_reference', 96)->unique('checkout_intents_reference_unique');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status');
            $table->string('provider_reference', 191)->nullable();
            $table->text('authorization_url')->nullable();
            $table->string('access_code', 191)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['invoice_id', 'provider', 'provider_account_key'], 'checkout_intents_invoice_provider_unique');
            $table->index(['provider', 'provider_account_key', 'status'], 'checkout_intents_provider_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_intents');
    }
};
