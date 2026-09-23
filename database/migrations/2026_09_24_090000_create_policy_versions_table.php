<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policy_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('document_type');
            $table->string('audience')->nullable();
            $table->string('version', 100);
            $table->string('title');
            $table->longText('content');
            $table->char('sha256', 64);
            $table->boolean('is_synthetic_fixture')->default(false);
            $table->string('status');
            $table->timestamp('effective_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('approved_by_reference')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('approval_evidence_reference')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();

            $table->unique(['document_type', 'version'], 'policy_versions_document_version_uq');
            $table->index(['document_type', 'status'], 'policy_versions_document_status_idx');
            $table->index(['document_type', 'published_at'], 'policy_versions_document_published_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_versions');
    }
};
