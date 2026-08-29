<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_asset_rights', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_asset_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provenance');
            $table->string('status');
            $table->json('allowed_uses');
            $table->boolean('attribution_required')->default(false);
            $table->string('license_identifier')->nullable();
            $table->foreignId('declared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('declared_at')->nullable();
            $table->string('verified_by_reference')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('restriction_reason')->nullable();
            $table->timestamps();

            $table->index(['church_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_asset_rights');
    }
};
