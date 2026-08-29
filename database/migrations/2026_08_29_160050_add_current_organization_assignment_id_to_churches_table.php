<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('churches', function (Blueprint $table): void {
            $table->foreignId('current_organization_assignment_id')
                ->nullable()
                ->unique()
                ->constrained('church_organization_assignments')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('churches', function (Blueprint $table): void {
            $table->dropForeign(['current_organization_assignment_id']);
            $table->dropUnique(['current_organization_assignment_id']);
        });

        Schema::table('churches', function (Blueprint $table): void {
            $table->dropColumn('current_organization_assignment_id');
        });
    }
};
