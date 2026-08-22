<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->string('theme');
            $table->json('snapshot');
            $table->char('working_fingerprint', 64);
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at');
            $table->timestamps();

            $table->index(['church_id', 'published_at']);
        });

        Schema::table('website_settings', function (Blueprint $table) {
            $table->foreignId('current_publication_id')
                ->nullable()
                ->after('footer_note')
                ->constrained('website_publications')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('website_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_publication_id');
        });

        Schema::dropIfExists('website_publications');
    }
};
