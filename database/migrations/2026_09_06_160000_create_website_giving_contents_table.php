<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * K-WEB-V1-001D-C §42-46 — Giving is an informational singleton page,
 * following the exact `website_home_contents`/`website_about_contents`
 * one-row-per-Church pattern (`church_id` unique). No transactional
 * field of any kind exists here: no donation, payment, recurring-gift,
 * receipt, or country-specific banking field. `giving_url` is an
 * optional external destination the Church already controls
 * independently — Keryon never becomes merchant-of-record (§42/§46).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_giving_contents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            $table->string('headline')->nullable();
            $table->text('body')->nullable();

            $table->foreignId('image_id')
                ->nullable()
                ->constrained('media_assets')
                ->nullOnDelete();
            $table->string('image_alt_override')->nullable();

            $table->string('cta_label')->nullable();
            $table->string('giving_url')->nullable();
            $table->text('additional_instructions')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_giving_contents');
    }
};
