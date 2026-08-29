<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('church_organization_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->restrictOnDelete();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('organization_unit_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->unsignedBigInteger('accepted_by')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('effective_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('reason', 500)->nullable();
            $table->timestamps();

            $table->index(['church_id', 'status', 'requested_at'], 'church_org_assign_church_status_req_idx');
            $table->index(['organization_id', 'status', 'requested_at'], 'church_org_assign_org_status_req_idx');
            $table->index(['organization_unit_id', 'status', 'requested_at'], 'church_org_assign_unit_status_req_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('church_organization_assignments');
    }
};
