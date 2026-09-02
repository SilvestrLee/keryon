<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_mfa_credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('platform_membership_id')->unique('platform_mfa_credentials_membership_unique')->constrained('platform_memberships')->cascadeOnDelete();
            $table->text('totp_secret')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->json('recovery_code_hashes')->nullable();
            $table->timestamp('recovery_codes_generated_at')->nullable();
            $table->timestamp('invalidated_at')->nullable();
            $table->unsignedInteger('credential_version')->default(1);
            $table->timestamps();

            $table->index(['confirmed_at', 'invalidated_at'], 'platform_mfa_credentials_state_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_mfa_credentials');
    }
};
