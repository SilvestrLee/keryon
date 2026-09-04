<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * K-ORG-COMMS-001E §20 — a small, additive, non-weakening extension to
 * the existing Church Media rights gate: `media_asset_rights` already
 * carries `attribution_required` (boolean) but had no free-text field to
 * preserve an actual attribution string. Organization Communication
 * assets carry one (`OrganizationCommunicationAsset::attribution_text`)
 * that must survive import (§20/§32) — rather than fabricate or discard
 * it, this adds the narrowest possible column so any asset's rights
 * evidence (not just imported ones) can carry it. Nothing about the
 * existing rights gate (`AssetRightsPolicy`) changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_asset_rights', function (Blueprint $table): void {
            $table->string('attribution_text')->nullable()->after('attribution_required');
        });
    }

    public function down(): void
    {
        Schema::table('media_asset_rights', function (Blueprint $table): void {
            $table->dropColumn('attribution_text');
        });
    }
};
