<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * K-WEB-V1-001D-C §32-41 — the pastor/Church-authored books/resources
 * catalogue. Named `ChurchPublication`/`church_publications` (not
 * `Publication`, and never `WebsitePublication`, which already means
 * the immutable Website deployment/publication-evidence record — a
 * Product-Office-locked naming decision, §32). `price_text` is bounded
 * display metadata, not transactional/accounting data — Keryon does not
 * process this content's sale; `purchase_url` is an optional external
 * destination only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('church_publications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('title');
            $table->string('author')->nullable();
            $table->string('publication_type')->default('book');
            $table->text('description')->nullable();

            $table->foreignId('cover_id')
                ->nullable()
                ->constrained('media_assets')
                ->nullOnDelete();
            $table->string('cover_alt_override')->nullable();

            $table->string('price_text')->nullable();
            $table->string('purchase_url')->nullable();

            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);

            $table->softDeletes();
            $table->timestamps();

            $table->index(['church_id', 'sort_order'], 'church_publications_church_sort_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('church_publications');
    }
};
