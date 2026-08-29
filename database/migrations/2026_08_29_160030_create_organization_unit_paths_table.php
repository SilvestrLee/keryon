<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_unit_paths', function (Blueprint $table): void {
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('ancestor_id')->constrained('organization_units')->cascadeOnDelete();
            $table->foreignId('descendant_id')->constrained('organization_units')->cascadeOnDelete();
            $table->unsignedInteger('depth');

            $table->primary(['ancestor_id', 'descendant_id']);
            $table->index(['organization_id', 'ancestor_id', 'depth', 'descendant_id'], 'org_unit_paths_ancestor_index');
            $table->index(['organization_id', 'descendant_id', 'depth', 'ancestor_id'], 'org_unit_paths_descendant_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_unit_paths');
    }
};
