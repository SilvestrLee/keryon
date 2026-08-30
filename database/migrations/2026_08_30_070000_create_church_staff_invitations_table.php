<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('church_staff_invitations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('church_id')->constrained('churches')->cascadeOnDelete();
            $table->string('email_normalized');
            $table->foreignId('prospective_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status');
            $table->string('pending_identity', 64)->nullable();
            $table->string('token_hash', 64)->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('invited_by_membership_id')->constrained('church_memberships')->restrictOnDelete();
            $table->uuid('idempotency_key');
            $table->string('acceptance_idempotency_key')->nullable();
            $table->string('terms_version', 100)->nullable();
            $table->string('privacy_version', 100)->nullable();
            $table->timestamp('legal_accepted_at')->nullable();
            $table->foreignId('legal_accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('replaces_invitation_id')->nullable()->constrained('church_staff_invitations')->nullOnDelete();
            $table->timestamps();

            $table->unique('uuid', 'staff_invites_uuid_uq');
            $table->unique('idempotency_key', 'staff_invites_idempotency_uq');
            $table->unique('pending_identity', 'staff_invites_pending_identity_uq');
            $table->unique('token_hash', 'staff_invites_token_hash_uq');
            $table->index(['church_id', 'email_normalized'], 'staff_invites_church_email_idx');
            $table->index(['church_id', 'status', 'created_at'], 'staff_invites_church_status_idx');
            $table->index(['prospective_user_id', 'status'], 'staff_invites_user_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('church_staff_invitations');
    }
};
