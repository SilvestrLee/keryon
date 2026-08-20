<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Additive — see K-FAITHFLOW-001D §38. Provenance belongs on the append-only
// usage/audit table (whose whole purpose is provider/model/prompt-generation
// provenance, K-FAITHFLOW-001D §39), not duplicated onto the mutable
// faithflow_outputs row. Neither faithflow_outputs nor faithflow_usage had a
// prompt-version field before this; no other 001B/001C migration is touched.
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('faithflow_usage', function (Blueprint $table) {
            $table->string('prompt_version')->nullable()->after('model');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('faithflow_usage', function (Blueprint $table) {
            $table->dropColumn('prompt_version');
        });
    }
};
