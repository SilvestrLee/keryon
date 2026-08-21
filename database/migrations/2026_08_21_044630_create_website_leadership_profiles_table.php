<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * K-CHURCHWEB-001B §19 ("Leadership" section) + §21 (repeatable
 * structure — modeled relationally for ordering/validation/tenant safety,
 * never a JSON blob). `category` groups by the Blueprint's own Pastor/
 * Minister/Elder/Team breakdown; the specific title stays free text on
 * `role_title` since real church titles vary too much for a rigid enum.
 * `photo_alt_override` is this profile's usage-level override of
 * `photo_id`'s asset-level default alt text — see §15.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_leadership_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('church_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('category');
            $table->string('role_title')->nullable();
            $table->text('bio')->nullable();

            $table->foreignId('photo_id')
                ->nullable()
                ->constrained('media_assets')
                ->nullOnDelete();
            $table->string('photo_alt_override')->nullable();

            $table->unsignedInteger('sort_order')->default(0);

            $table->softDeletes();
            $table->timestamps();

            $table->index(['church_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_leadership_profiles');
    }
};
