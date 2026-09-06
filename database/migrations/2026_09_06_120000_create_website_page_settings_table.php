<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * K-WEB-V1-001D-B §23/§24 — the one narrow table this milestone
 * authorizes. Bounded Church-level page configuration only: whether an
 * optional canonical page type is enabled, its navigation order, and an
 * optional navigation-label override. No generic page content, no JSON,
 * no arbitrary schema — see the milestone report for the full rationale.
 *
 * Absence of a row for a given (church_id, page_type) pair is a
 * meaningful, deliberate state, not an oversight — it means "use the
 * legacy/default behavior for this page type" (§33/§34). Rows are only
 * ever created when a Church's effective configuration genuinely
 * diverges from the platform default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_page_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('page_type');
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('nav_order')->nullable();
            $table->string('navigation_label', 60)->nullable();
            $table->timestamps();

            $table->unique(['church_id', 'page_type'], 'website_page_settings_church_page_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_page_settings');
    }
};
