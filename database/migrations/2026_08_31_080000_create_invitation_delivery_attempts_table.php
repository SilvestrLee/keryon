<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitation_delivery_attempts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->string('subject_type');
            $table->foreignId('church_activation_id')->nullable()->constrained('church_activations')->cascadeOnDelete();
            $table->foreignId('church_staff_invitation_id')->nullable()->constrained('church_staff_invitations')->cascadeOnDelete();
            $table->string('recipient_email');
            $table->string('status');
            $table->string('token_fingerprint', 64);
            $table->text('sensitive_payload')->nullable();
            $table->timestamp('sensitive_payload_cleared_at')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('provider_accepted_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->timestamp('complained_at')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->string('failure_category')->nullable();
            $table->string('provider_message_reference')->nullable();
            $table->string('provider_account_key')->nullable();
            $table->timestamps();

            $table->unique('uuid', 'invite_delivery_uuid_uq');
            $table->unique(['subject_type', 'token_fingerprint'], 'invite_delivery_subject_token_uq');
            $table->index(['church_activation_id', 'status'], 'invite_delivery_activation_status_idx');
            $table->index(['church_staff_invitation_id', 'status'], 'invite_delivery_staff_status_idx');
            $table->index(['recipient_email', 'requested_at'], 'invite_delivery_recipient_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitation_delivery_attempts');
    }
};
