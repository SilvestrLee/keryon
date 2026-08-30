<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->restrictOnDelete();
            $table->foreignId('plan_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('price_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('status')->default('active');
            $table->timestamp('effective_from');
            $table->timestamp('effective_until')->nullable();
            $table->timestamps();

            $table->unique('subscription_id', 'subscription_items_base_unique');
            $table->index(['plan_version_id', 'status'], 'subscription_items_plan_status_idx');
            $table->index(['price_id', 'status'], 'subscription_items_price_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_items');
    }
};
