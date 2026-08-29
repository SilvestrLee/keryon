<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('marketplace_category_id')->constrained('marketplace_categories')->restrictOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('short_description');
            $table->text('description')->nullable();
            $table->string('access_type');
            $table->string('publication_status')->default('draft');
            $table->string('creator_name')->nullable();
            $table->string('publisher_name')->default('Keryon');
            $table->timestamp('published_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['publication_status', 'access_type']);
            $table->index(['marketplace_category_id', 'publication_status'], 'marketplace_item_category_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_items');
    }
};
