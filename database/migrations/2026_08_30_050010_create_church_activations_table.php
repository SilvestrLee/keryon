<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('church_activations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('church_activations_uuid_uq');
            $table->foreignId('church_id')->unique('church_activations_church_uq')->constrained()->restrictOnDelete();
            $table->string('prospective_primary_email');
            $table->foreignId('prospective_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status');
            $table->char('token_hash', 64)->nullable()->unique('church_activations_token_uq');
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('invitation_sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('provisioned_by_reference');
            $table->string('provisioning_origin');
            $table->foreignId('pricing_market_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('plan_version_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('price_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('billing_interval');
            $table->string('payer_type');
            $table->foreignId('payer_church_id')->nullable()->constrained('churches')->restrictOnDelete();
            $table->foreignId('payer_organization_id')->nullable()->constrained('organizations')->restrictOnDelete();
            $table->foreignId('payer_organization_unit_id')->nullable()->constrained('organization_units')->restrictOnDelete();
            $table->string('idempotency_key')->unique('church_activations_idempotency_uq');
            $table->string('acceptance_idempotency_key')->nullable()->unique('church_activations_acceptance_uq');
            $table->string('terms_version')->nullable();
            $table->string('privacy_version')->nullable();
            $table->timestamp('legal_accepted_at')->nullable();
            $table->foreignId('legal_accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->restrictOnDelete();
            $table->foreignId('organization_unit_id')->nullable()->constrained('organization_units')->restrictOnDelete();
            $table->timestamps();
            $table->index(['prospective_primary_email', 'status'], 'church_activations_email_status_idx');
            $table->index(['status', 'token_expires_at'], 'church_activations_status_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('church_activations');
    }
};
