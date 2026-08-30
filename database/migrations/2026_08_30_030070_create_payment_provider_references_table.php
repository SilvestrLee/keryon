<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_provider_references', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 32);
            $table->string('provider_account_key', 96);
            $table->string('reference_type', 32);
            $table->string('provider_reference', 191);
            $table->string('local_type', 64);
            $table->unsignedBigInteger('local_id');
            $table->timestamps();
            $table->unique(['provider', 'provider_account_key', 'provider_reference'], 'provider_refs_external_unique');
            $table->unique(['provider', 'provider_account_key', 'reference_type', 'local_type', 'local_id'], 'provider_refs_local_unique');
            $table->index(['local_type', 'local_id'], 'provider_refs_local_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_provider_references');
    }
};
