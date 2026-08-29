<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_acquisitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->restrictOnDelete();
            $table->foreignId('marketplace_item_id')->constrained('marketplace_items')->restrictOnDelete();
            $table->foreignId('marketplace_source_version_id')->constrained('marketplace_source_versions')->restrictOnDelete();
            $table->string('access_type');
            $table->string('acquisition_basis');
            $table->string('entitlement_reference')->nullable();
            $table->foreignId('acquired_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acquired_at');
            $table->timestamps();

            $table->unique(['church_id', 'marketplace_item_id']);
            $table->index(['church_id', 'acquired_at']);
            $table->index(['church_id', 'marketplace_source_version_id'], 'marketplace_acquisition_source_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_acquisitions');
    }
};
