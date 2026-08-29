<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_role_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_membership_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_unit_id')->constrained()->restrictOnDelete();
            $table->string('role');
            $table->string('status')->default('active');
            $table->timestamp('assigned_at');
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['organization_membership_id', 'organization_unit_id', 'role'],
                'org_role_assignments_membership_unit_role_unique',
            );
            $table->index(
                ['organization_membership_id', 'status', 'role'],
                'org_role_assignments_membership_status_role_index',
            );
            $table->index(
                ['organization_unit_id', 'status', 'role'],
                'org_role_assignments_unit_status_role_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_role_assignments');
    }
};
