<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('billing_account_id')->constrained()->restrictOnDelete();
            $table->string('billing_name');
            $table->string('legal_name')->nullable();
            $table->string('billing_email')->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->string('postal_code')->nullable();
            $table->char('country_code', 2)->nullable();
            $table->char('preferred_currency', 3)->nullable();
            $table->string('tax_identifier_type')->nullable();
            $table->text('tax_identifier_value')->nullable();
            $table->timestamp('effective_from');
            $table->timestamp('effective_until')->nullable();
            $table->timestamps();
            $table->index(['billing_account_id', 'effective_from'], 'billing_profiles_account_effective_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_profiles');
    }
};
