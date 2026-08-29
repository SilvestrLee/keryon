<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_source_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('marketplace_item_id')->constrained('marketplace_items')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('disk')->default('marketplace');
            $table->string('storage_key')->unique();
            $table->string('original_filename');
            $table->string('mime_type');
            $table->string('extension', 12);
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64);
            $table->string('validation_status')->default('pending');
            $table->string('availability_status')->default('draft');
            $table->json('compatibility_metadata')->nullable();
            $table->string('creator_name')->nullable();
            $table->string('rightsholder_name')->nullable();
            $table->string('license_reference')->nullable();
            $table->json('licensing_metadata')->nullable();
            $table->json('font_metadata')->nullable();
            $table->timestamp('available_at')->nullable();
            $table->timestamps();

            $table->unique(['marketplace_item_id', 'version']);
            $table->index(['marketplace_item_id', 'availability_status', 'validation_status'], 'marketplace_source_availability_index');
            $table->index('sha256');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_source_versions');
    }
};
