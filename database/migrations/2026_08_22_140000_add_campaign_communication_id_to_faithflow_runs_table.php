<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faithflow_runs', function (Blueprint $table): void {
            $table->foreignId('campaign_communication_id')
                ->nullable()
                ->after('created_by')
                ->constrained('campaign_communications')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('faithflow_runs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('campaign_communication_id');
        });
    }
};
