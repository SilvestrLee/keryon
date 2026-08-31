<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_publication_provenances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('website_publication_id');
            $table->foreignId('website_content_provenance_id');
            $table->timestamps();

            $table->unique(
                ['website_publication_id', 'website_content_provenance_id'],
                'web_publication_provenance_uq',
            );
            $table->index(
                ['church_id', 'website_content_provenance_id', 'website_publication_id'],
                'web_publication_lineage_idx',
            );
            $table->foreign('website_publication_id', 'web_pub_prov_publication_fk')
                ->references('id')->on('website_publications')->cascadeOnDelete();
            $table->foreign('website_content_provenance_id', 'web_pub_prov_lineage_fk')
                ->references('id')->on('website_content_provenances')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_publication_provenances');
    }
};
