<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('prices_uuid_unique');
            $table->foreignId('price_book_id')->constrained()->restrictOnDelete();
            $table->foreignId('plan_version_id')->constrained()->restrictOnDelete();
            $table->string('billing_interval');
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->string('status')->default('active');
            $table->string('tax_behavior')->default('unspecified');
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->timestamps();

            $table->unique(['price_book_id', 'plan_version_id', 'billing_interval'], 'prices_book_plan_interval_unique');
            $table->index(['plan_version_id', 'billing_interval', 'status'], 'prices_plan_interval_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prices');
    }
};
