<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * K-CHURCHWEB-001B §7-§11 — the shared Church Brand Profile. One profile
 * per Church (`church_id` unique — enforced at the database level, not
 * only in application code), consumable by Website now and by Design
 * Studio/Campaigns later (see the report §31 seam). Fields are the
 * smallest durable v1 set actually justified by Proclaim/Website today —
 * "secondary logo" from early brainstorming was deliberately excluded;
 * see the report §4 for the full field-inclusion reasoning.
 *
 * `mark_media_id` is the square icon/symbol mark, genuinely distinct from
 * `primary_logo_media_id` (a full wordmark/lockup) — e.g. for browser-tab
 * icons or social avatars, not redundant with the primary logo.
 *
 * Colors are plain nullable strings validated at the model level (hex
 * format only — see ChurchBrandProfile::validateHexColor()), not a full
 * contrast engine — that remains a later theme-rendering responsibility
 * per §10. Typography is bounded to BrandFontChoice — see §9.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('church_brand_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('church_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('primary_logo_media_id')
                ->nullable()
                ->constrained('media_assets')
                ->nullOnDelete();

            $table->foreignId('mark_media_id')
                ->nullable()
                ->constrained('media_assets')
                ->nullOnDelete();

            $table->string('primary_color')->nullable();
            $table->string('secondary_color')->nullable();
            $table->string('accent_color')->nullable();

            $table->string('heading_font')->nullable();
            $table->string('body_font')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('church_brand_profiles');
    }
};
