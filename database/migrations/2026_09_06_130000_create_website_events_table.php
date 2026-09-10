<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * K-WEB-V1-001D-C §17-24 — Events is informational Website publishing
 * only, never event-management ERP: no registration, RSVP, ticketing,
 * attendance, capacity, or check-in field exists here or anywhere in
 * this milestone. `starts_at`/`ends_at` use the application's existing
 * datetime column convention (no separate timezone subsystem — the
 * Church's own timezone, already used elsewhere in this codebase,
 * governs display). `sort_order` is a secondary tie-breaker only — the
 * public page's primary ordering is `starts_at ASC` (§83).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('title');
            $table->text('summary')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->string('venue')->nullable();

            $table->foreignId('image_id')
                ->nullable()
                ->constrained('media_assets')
                ->nullOnDelete();
            $table->string('image_alt_override')->nullable();

            $table->string('cta_label')->nullable();
            $table->string('cta_url')->nullable();

            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);

            $table->softDeletes();
            $table->timestamps();

            $table->index(['church_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_events');
    }
};
