<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('designs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_communication_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('primary_logo_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->foreignId('mark_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('template_key');
            $table->unsignedInteger('template_version');
            $table->string('purpose');
            $table->string('variant')->default('default');
            $table->json('inputs');
            $table->json('brand_snapshot');
            $table->string('state')->default('draft');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['church_id', 'state']);
            $table->index(['church_id', 'template_key', 'template_version']);
            $table->index(['church_id', 'content_item_id']);
            $table->index(['church_id', 'campaign_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('designs');
    }
};
