<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('church_access_audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained('churches')->cascadeOnDelete();
            $table->string('event_type');
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('actor_membership_id')->nullable()->constrained('church_memberships')->nullOnDelete();
            $table->json('previous_state')->nullable();
            $table->json('new_state')->nullable();
            $table->timestamp('occurred_at');

            $table->index(['church_id', 'event_type', 'occurred_at'], 'staff_access_audit_church_event_idx');
            $table->index(['subject_type', 'subject_id', 'occurred_at'], 'staff_access_audit_subject_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('church_access_audit_events');
    }
};
