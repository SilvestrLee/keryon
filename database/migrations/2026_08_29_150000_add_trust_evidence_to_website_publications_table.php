<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_publications', function (Blueprint $table): void {
            $table->string('destination')->default('church_website')->after('church_id');
            $table->foreignId('previous_publication_id')
                ->nullable()
                ->after('working_fingerprint')
                ->constrained('website_publications')
                ->nullOnDelete();
            $table->json('trust_evidence')->nullable()->after('previous_publication_id');
        });
    }

    public function down(): void
    {
        Schema::table('website_publications', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('previous_publication_id');
            $table->dropColumn(['destination', 'trust_evidence']);
        });
    }
};
