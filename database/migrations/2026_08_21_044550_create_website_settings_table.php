<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * K-CHURCHWEB-001B §19 ("Global" section) + §25 (theme-selection seam).
 * One row per Church (`church_id` unique). `theme` stores a
 * `WebsiteTheme` enum value — plain string column, zero relationship to
 * any Website Content table, so changing it can never mutate content
 * (K-CHURCHWEB-001B acceptance criteria: "theme selection... cannot
 * mutate content"). `footer_note` is the one genuinely Website-specific
 * "Global" field left after applying the institutional-ownership test to
 * Church Name/Logo/Contact/Service Times/Social Links — all of which
 * already live on Church/ChurchBrandProfile/ChurchServiceTime/
 * ChurchSocialLink. See the report §5.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_settings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('church_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            $table->string('theme')->default('proclaim');
            $table->text('footer_note')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_settings');
    }
};
