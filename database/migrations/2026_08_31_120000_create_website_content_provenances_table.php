<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_content_provenances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->string('destination', 40);
            $table->unsignedBigInteger('website_record_id');
            $table->foreignId('content_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_communication_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('design_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('media_asset_id')->nullable()->constrained()->nullOnDelete();
            $table->char('source_fingerprint', 64);
            $table->char('destination_before_fingerprint', 64);
            $table->char('destination_after_fingerprint', 64);
            $table->timestamp('source_approved_at');
            $table->foreignId('applied_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('applied_at');
            $table->timestamps();

            $table->index(['church_id', 'destination', 'website_record_id'], 'web_prov_destination_idx');
            $table->unique(['church_id', 'destination', 'content_item_id', 'source_fingerprint'], 'web_prov_source_version_uq');
            $table->index(['campaign_communication_id', 'applied_at'], 'web_prov_communication_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_content_provenances');
    }
};
