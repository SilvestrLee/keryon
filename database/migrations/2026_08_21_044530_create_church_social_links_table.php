<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * K-CHURCHWEB-001B §24 — social handles describe the Church, not merely
 * its Website (would remain true if Website disappeared), so this is a
 * Church-owned institutional table, not Website content. A plain public
 * URL/handle record only — never an OAuth-connected publishing account;
 * that is a separate, later, explicitly out-of-scope concept.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('church_social_links', function (Blueprint $table) {
            $table->id();

            $table->foreignId('church_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('platform');
            $table->string('url');
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['church_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('church_social_links');
    }
};
