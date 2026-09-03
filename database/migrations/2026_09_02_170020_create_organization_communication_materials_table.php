<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_communication_materials', function (Blueprint $table): void {
            $table->id();
            // Explicit short name: the auto-generated `{table}_{column}_foreign`
            // name exceeds MySQL's 64-char identifier limit.
            $table->foreignId('organization_communication_revision_id')
                ->constrained('organization_communication_revisions', 'id', 'org_comm_materials_revision_fk')
                ->cascadeOnDelete();
            $table->string('type', 48);
            $table->string('title')->nullable();
            $table->text('body');
            $table->unsignedInteger('sort_order');
            $table->timestamps();

            $table->unique(
                ['organization_communication_revision_id', 'sort_order'],
                'org_comm_materials_order_unique',
            );
            $table->index(
                ['organization_communication_revision_id', 'type'],
                'org_comm_materials_type_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_communication_materials');
    }
};
