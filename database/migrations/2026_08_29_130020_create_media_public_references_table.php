<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_public_references', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_rendition_id')->constrained()->cascadeOnDelete();
            $table->string('consumer_type');
            $table->unsignedBigInteger('consumer_id');
            $table->string('usage_key');
            $table->timestamp('activated_at');
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();
            $table->unique(['consumer_type', 'consumer_id', 'usage_key'], 'media_public_reference_usage_unique');
            $table->index(['media_rendition_id', 'deactivated_at'], 'media_public_reference_active_index');
            $table->index(['church_id', 'consumer_type', 'consumer_id'], 'media_public_reference_consumer_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_public_references');
    }
};
