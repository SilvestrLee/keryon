<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_communications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('governing_unit_id')->constrained('organization_units')->restrictOnDelete();
            $table->foreignId('created_by_organization_membership_id')
                // Explicit short name: MySQL's 64-char identifier limit
                // rejects the auto-generated name for this column.
                ->constrained('organization_memberships', 'id', 'org_comms_creator_membership_fk')
                ->restrictOnDelete();
            $table->string('kind', 32);
            $table->string('state', 32)->default('draft');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'state', 'updated_at'], 'org_comms_org_state_updated_idx');
            $table->index(['governing_unit_id', 'state'], 'org_comms_unit_state_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_communications');
    }
};
