<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_item_id')->nullable()->constrained('content_items')->nullOnDelete();
            $table->string('title');
            $table->text('purpose')->nullable();
            $table->string('channel');
            $table->dateTime('target_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['church_id', 'campaign_id']);
            $table->index(['church_id', 'channel']);
            $table->index(['church_id', 'target_at']);
            $table->index(['campaign_id', 'sort_order']);
            $table->unique(['campaign_id', 'content_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_communications');
    }
};
