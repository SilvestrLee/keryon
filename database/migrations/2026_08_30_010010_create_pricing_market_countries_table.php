<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_market_countries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pricing_market_id')->constrained()->restrictOnDelete();
            $table->char('country_code', 2)->unique('pricing_country_code_unique');
            $table->timestamps();

            $table->index(['pricing_market_id', 'country_code'], 'pricing_country_market_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_market_countries');
    }
};
