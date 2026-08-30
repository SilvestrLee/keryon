<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_books', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('price_books_uuid_unique');
            $table->foreignId('pricing_market_id')->constrained()->restrictOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('status')->default('draft');
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['pricing_market_id', 'code'], 'price_books_market_code_unique');
            $table->index(['pricing_market_id', 'status', 'effective_from', 'effective_until'], 'price_books_market_effective_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_books');
    }
};
