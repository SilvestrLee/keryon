<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_downloads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->restrictOnDelete();
            $table->foreignId('marketplace_acquisition_id')->constrained('marketplace_acquisitions')->restrictOnDelete();
            $table->foreignId('marketplace_source_version_id')->constrained('marketplace_source_versions')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('outcome');
            $table->string('failure_code')->nullable();
            $table->timestamp('issued_at');
            $table->timestamps();

            $table->index(['church_id', 'issued_at']);
            $table->index(['marketplace_acquisition_id', 'issued_at'], 'marketplace_download_acquisition_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_downloads');
    }
};
