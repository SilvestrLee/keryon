<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_renditions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_asset_id')->constrained()->restrictOnDelete();
            $table->string('variant');
            $table->string('state')->default('active');
            $table->string('disk');
            $table->string('path');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->char('sha256', 64);
            $table->char('source_sha256', 64);
            $table->timestamp('activated_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['media_asset_id', 'source_sha256', 'variant'], 'media_rendition_source_variant_unique');
            $table->index(['state', 'activated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_renditions');
    }
};
