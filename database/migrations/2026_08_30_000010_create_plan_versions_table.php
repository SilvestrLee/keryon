<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_versions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('plan_versions_uuid_unique');
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('version_code');
            $table->string('status')->default('draft');
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['plan_id', 'version_code'], 'plan_versions_plan_code_unique');
            $table->index(['status', 'effective_from', 'effective_until'], 'plan_versions_status_effective_idx');
            $table->index(['plan_id', 'published_at'], 'plan_versions_plan_published_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_versions');
    }
};
