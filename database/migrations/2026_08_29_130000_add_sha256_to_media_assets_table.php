<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_assets', fn (Blueprint $table) => $table->char('sha256', 64)->nullable()->after('size'));
    }

    public function down(): void
    {
        Schema::table('media_assets', fn (Blueprint $table) => $table->dropColumn('sha256'));
    }
};
