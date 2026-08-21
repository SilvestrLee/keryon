<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * K-CHURCHWEB-001B §19 ("About" section). One row per Church. Leadership
 * Introduction is the short copy that precedes the repeatable
 * WebsiteLeadershipProfile list — the profiles themselves live in their
 * own table (§21, repeatable structure).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_about_contents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('church_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            $table->text('church_story')->nullable();
            $table->text('vision')->nullable();
            $table->text('mission')->nullable();
            $table->text('leadership_introduction')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_about_contents');
    }
};
