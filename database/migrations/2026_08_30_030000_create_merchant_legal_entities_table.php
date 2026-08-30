<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_legal_entities', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('merchant_entities_uuid_unique');
            $table->string('code')->unique('merchant_entities_code_unique');
            $table->string('display_name');
            $table->string('legal_name');
            $table->char('country_code', 2);
            $table->string('status')->default('active');
            $table->string('invoice_series');
            $table->timestamps();
            $table->index(['status', 'country_code'], 'merchant_entities_status_country_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_legal_entities');
    }
};
