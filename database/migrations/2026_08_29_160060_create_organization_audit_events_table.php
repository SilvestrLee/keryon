<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('event_type');
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('church_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('organization_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->json('previous_state')->nullable();
            $table->json('new_state')->nullable();
            $table->timestamp('occurred_at');

            $table->index(['organization_id', 'occurred_at'], 'org_audit_org_occurred_idx');
            $table->index(['organization_id', 'event_type', 'occurred_at'], 'org_audit_org_event_occurred_idx');
            $table->index(['subject_type', 'subject_id', 'occurred_at'], 'org_audit_subject_occurred_idx');
            $table->index(['church_id', 'occurred_at'], 'org_audit_church_occurred_idx');
            $table->index(['organization_unit_id', 'occurred_at'], 'org_audit_unit_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_audit_events');
    }
};
