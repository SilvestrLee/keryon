<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_previews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('marketplace_item_id')->constrained('marketplace_items')->cascadeOnDelete();
            $table->foreignId('marketplace_source_version_id')->nullable()->constrained('marketplace_source_versions')->restrictOnDelete();
            $table->string('type');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('disk')->default('marketplace');
            $table->string('storage_key')->unique();
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->string('alt_text');
            $table->char('sha256', 64);
            $table->timestamps();

            $table->index(['marketplace_item_id', 'type', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_previews');
    }
};
