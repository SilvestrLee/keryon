<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_markets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('pricing_markets_uuid_unique');
            $table->char('code', 2)->unique('pricing_markets_code_unique');
            $table->string('name');
            $table->char('currency', 3);
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['status', 'code'], 'pricing_markets_status_code_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_markets');
    }
};
