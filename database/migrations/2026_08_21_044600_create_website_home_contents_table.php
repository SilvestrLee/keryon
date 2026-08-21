<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * K-CHURCHWEB-001B §19 ("Home" section). One row per Church. Semantic
 * structured columns (Hero, Welcome, Scripture Highlight) per §20 — never
 * a generic JSON blob. "Featured Ministries", "Featured Campaign", and
 * "Latest News" from the Blueprint's own Home breakdown are deliberately
 * NOT included here yet — they depend on systems this milestone must not
 * touch (Ministries selection logic, Campaigns, Content Studio
 * publishing/handoff, all out of scope per §28-§30). See the report §5.
 *
 * `hero_image_alt_override` is the usage-level alt-text override for
 * `hero_image_id` — see §15's "both, with usage-level override" decision.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_home_contents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('church_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            $table->string('hero_heading')->nullable();
            $table->text('hero_subheading')->nullable();
            $table->string('hero_cta_label')->nullable();
            $table->string('hero_cta_url')->nullable();
            $table->foreignId('hero_image_id')
                ->nullable()
                ->constrained('media_assets')
                ->nullOnDelete();
            $table->string('hero_image_alt_override')->nullable();

            $table->string('welcome_heading')->nullable();
            $table->text('welcome_body')->nullable();

            $table->string('scripture_reference')->nullable();
            $table->text('scripture_text')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_home_contents');
    }
};
