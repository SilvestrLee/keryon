<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('design_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_asset_id')->constrained()->restrictOnDelete();
            $table->string('slot_key');
            $table->timestamps();

            $table->unique(['design_id', 'slot_key']);
            $table->index(['church_id', 'media_asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_media');
    }
};
