<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_outputs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('design_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_asset_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('format');
            $table->string('status')->default('pending');
            $table->string('failure_code')->nullable();
            $table->timestamp('rendered_at')->nullable();
            $table->timestamps();

            $table->unique(['design_id', 'format']);
            $table->index(['church_id', 'status']);
            $table->index(['church_id', 'media_asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_outputs');
    }
};
