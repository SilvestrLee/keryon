<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('merchant_legal_entity_id')->constrained()->restrictOnDelete();
            $table->string('series');
            $table->unsignedSmallInteger('sequence_year');
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
            $table->unique(['merchant_legal_entity_id', 'series', 'sequence_year'], 'invoice_sequences_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_sequences');
    }
};
