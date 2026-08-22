<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * K-CHURCHWEB-001B §19 ("Ministries" section) + §21 (repeatable
 * structure). `image_alt_override` is this ministry's usage-level
 * override of `image_id`'s asset-level default alt text — see §15.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_ministries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('church_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');
            $table->text('description')->nullable();

            $table->foreignId('image_id')
                ->nullable()
                ->constrained('media_assets')
                ->nullOnDelete();
            $table->string('image_alt_override')->nullable();

            $table->unsignedInteger('sort_order')->default(0);

            $table->softDeletes();
            $table->timestamps();

            $table->index(['church_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_ministries');
    }
};
