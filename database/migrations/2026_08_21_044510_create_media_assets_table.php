<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * K-CHURCHWEB-001B §12-§16 — the minimal institutional media domain
 * primitive. Deliberately named `media_assets`, not `website_images` —
 * Website is one consumer among future others (Design Studio as
 * producer, Campaigns as another consumer). See §13.
 *
 * `uuid` is the storage-path identity segment (tenants/{church_id}/media/
 * {uuid}/{filename}, per docs/06-Engineering/Media_Path_Strategy.md) —
 * generated before save, so the path never depends on the row's
 * auto-increment ID being known first, and so storage keys aren't
 * sequentially enumerable. `disk`/`path` record exactly where the file
 * physically lives; no Spatie Media Library — see the K-CHURCHWEB-001B
 * report §10 for why a package was not installed for this milestone.
 *
 * `alt_text` is the asset-level default — see §15's alt-text ownership
 * decision (both asset-level default AND usage-level override; the
 * override columns live on whatever references a MediaAsset, e.g.
 * WebsiteHomeContent::hero_image_alt_override).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('church_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->uuid('uuid')->unique();

            $table->string('disk');
            $table->string('path');

            $table->string('original_filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            $table->string('alt_text')->nullable();

            // Attribution only — see the identical created_by pattern and
            // rationale already established on content_items.
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['church_id', 'mime_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
