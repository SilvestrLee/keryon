<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * K-CHURCHWEB-001B §22 — physical address is an institutional fact about
 * the church (true whether or not a website exists), not Website-owned
 * content. Added directly to `churches` alongside the existing
 * name/email/phone fields, rather than duplicated on a Website Contact
 * table. See the K-CHURCHWEB-001B report §5 for the full ownership
 * reasoning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('churches', function (Blueprint $table) {
            $table->text('address')->nullable()->after('website');
        });
    }

    public function down(): void
    {
        Schema::table('churches', function (Blueprint $table) {
            $table->dropColumn('address');
        });
    }
};
