<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * K-ORG-COMMS-001E §29/§33/§34 — bounded, explicit result-provenance:
 * one row per Church-owned record an import created, each with an
 * explicit nullable FK per destination type (never a generic polymorphic
 * `morphable`). Mirrors the existing `WebsiteContentProvenance` pattern
 * exactly (explicit `contentItem()`/`campaign()`/`mediaAsset()` `belongsTo`
 * columns) rather than introducing a second, competing provenance
 * architecture.
 *
 * Every row also carries the exact source material/asset it came from
 * (also nullable — a Campaign-creation row has neither) so lineage never
 * relies on titles or matching (§28): source material/asset → resulting
 * Church record, by immutable ID only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_communication_import_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_communication_import_id')
                ->constrained('organization_communication_imports', 'id', 'org_comm_import_results_import_fk')
                ->cascadeOnDelete();
            $table->foreignId('church_id')
                ->constrained('churches', 'id', 'org_comm_import_results_church_fk')
                ->restrictOnDelete();

            // Exactly one of these three is set per row (enforced by the
            // model, not a CHECK constraint — see K-ORG-COMMS-001E §68).
            $table->foreignId('content_item_id')
                ->nullable()
                ->constrained('content_items', 'id', 'org_comm_import_results_content_fk')
                ->nullOnDelete();
            $table->foreignId('campaign_id')
                ->nullable()
                ->constrained('campaigns', 'id', 'org_comm_import_results_campaign_fk')
                ->nullOnDelete();
            $table->foreignId('media_asset_id')
                ->nullable()
                ->constrained('media_assets', 'id', 'org_comm_import_results_media_fk')
                ->nullOnDelete();

            // The exact source that produced the row above (nullable —
            // a Campaign-creation row is sourced from the revision as a
            // whole, not one material/asset).
            $table->foreignId('organization_communication_material_id')
                ->nullable()
                ->constrained('organization_communication_materials', 'id', 'org_comm_import_results_material_fk')
                ->nullOnDelete();
            $table->foreignId('organization_communication_asset_id')
                ->nullable()
                ->constrained('organization_communication_assets', 'id', 'org_comm_import_results_asset_fk')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['organization_communication_import_id'], 'org_comm_import_results_import_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_communication_import_results');
    }
};
