<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('plans_uuid_unique');
            $table->string('slug')->unique('plans_slug_unique');
            $table->string('name');
            $table->string('status')->default('draft');
            $table->timestamps();

            $table->index(['status', 'name'], 'plans_status_name_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
