<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * K-CHURCHWEB-001B §19 ("Contact" section). One row per Church.
 * Deliberately thin: applying the institutional-ownership test (§22) to
 * the Blueprint's "Address, Phone, Email, Map, Office Hours" list moved
 * Address/Phone/Email to `churches` (already existed, or added this
 * milestone — see the address migration) since those facts remain true
 * whether or not a website exists. Only Office Hours and how the map is
 * presented are genuinely Website-presentation-specific — the map itself
 * is derived from Church::address at render time, no separate coordinate
 * storage needed for v1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_contact_contents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('church_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            $table->text('office_hours')->nullable();
            $table->string('map_embed_url')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_contact_contents');
    }
};
