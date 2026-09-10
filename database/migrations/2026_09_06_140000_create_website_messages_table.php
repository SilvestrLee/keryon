<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * K-WEB-V1-001D-C §25-31 — a lightweight public sermon/message
 * catalogue. `media_url` is an external destination (YouTube, Vimeo, a
 * podcast/audio platform) — Keryon does not host video/audio; no MIME
 * expansion, no upload subsystem exists here. `speaker` is plain
 * Website content metadata, never coupled to a Church staff/User record
 * (§134).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('title');
            $table->string('speaker')->nullable();
            $table->date('message_date')->nullable();
            $table->string('scripture_reference')->nullable();
            $table->text('summary')->nullable();

            $table->foreignId('image_id')
                ->nullable()
                ->constrained('media_assets')
                ->nullOnDelete();
            $table->string('image_alt_override')->nullable();

            $table->string('media_url')->nullable();

            $table->boolean('is_featured')->default(false);

            $table->softDeletes();
            $table->timestamps();

            $table->index(['church_id', 'message_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_messages');
    }
};
