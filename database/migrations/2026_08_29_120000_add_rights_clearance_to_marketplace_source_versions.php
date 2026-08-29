<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_source_versions', function (Blueprint $table): void {
            $table->string('rights_status')->default('pending')->after('availability_status');
            $table->text('source_provenance')->nullable()->after('font_metadata');
            $table->string('rights_evidence_reference')->nullable()->after('source_provenance');
            $table->string('rights_verified_by_type')->nullable()->after('rights_evidence_reference');
            $table->string('rights_verified_by_reference')->nullable()->after('rights_verified_by_type');
            $table->timestamp('rights_verified_at')->nullable()->after('rights_verified_by_reference');
            $table->string('rights_decided_by_type')->nullable()->after('rights_verified_at');
            $table->string('rights_decided_by_reference')->nullable()->after('rights_decided_by_type');
            $table->timestamp('rights_decided_at')->nullable()->after('rights_decided_by_reference');
            $table->text('rights_decision_reason')->nullable()->after('rights_decided_at');
            $table->index(['marketplace_item_id', 'rights_status'], 'marketplace_source_rights_index');
        });

        // Existing technical/publication state is not rights evidence.
        // Preserve published_at, acquisitions, downloads, and source history,
        // but stop legacy items from representing current publication.
        DB::table('marketplace_items')
            ->where('publication_status', 'published')
            ->update(['publication_status' => 'unpublished']);
    }

    public function down(): void
    {
        Schema::table('marketplace_source_versions', function (Blueprint $table): void {
            $table->dropIndex('marketplace_source_rights_index');
            $table->dropColumn([
                'rights_status',
                'source_provenance',
                'rights_evidence_reference',
                'rights_verified_by_type',
                'rights_verified_by_reference',
                'rights_verified_at',
                'rights_decided_by_type',
                'rights_decided_by_reference',
                'rights_decided_at',
                'rights_decision_reason',
            ]);
        });
    }
};
