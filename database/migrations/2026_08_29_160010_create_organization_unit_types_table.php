<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_unit_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('code');
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code'], 'org_unit_types_org_code_unique');
            $table->index(['organization_id', 'is_active', 'sort_order'], 'org_unit_types_org_active_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_unit_types');
    }
};
