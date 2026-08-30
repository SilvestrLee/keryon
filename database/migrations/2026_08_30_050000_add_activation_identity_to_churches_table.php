<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('churches', function (Blueprint $table): void {
            $table->char('operating_country_code', 2)->nullable()->after('timezone');
            $table->timestamp('activated_at')->nullable()->after('is_active');
            $table->index('operating_country_code', 'churches_operating_country_idx');
        });
    }

    public function down(): void
    {
        Schema::table('churches', function (Blueprint $table): void {
            $table->dropIndex('churches_operating_country_idx');
            $table->dropColumn(['operating_country_code', 'activated_at']);
        });
    }
};
