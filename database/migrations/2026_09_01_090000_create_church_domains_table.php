<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('church_domains', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('church_domains_uuid_unique');
            $table->foreignId('church_id')->constrained()->restrictOnDelete();
            $table->string('normalized_hostname', 253)->unique('church_domains_hostname_unique');
            $table->string('display_hostname', 253)->nullable();
            $table->string('status', 32)->default('pending_verification');
            $table->string('verification_method', 16)->default('txt');
            $table->char('verification_token_hash', 64);
            $table->timestamp('ownership_verified_at')->nullable();
            $table->timestamp('routing_verified_at')->nullable();
            $table->string('tls_status', 24)->default('not_started');
            $table->timestamp('tls_ready_at')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamp('last_checked_at')->nullable();
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->string('failure_code', 40)->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('created_by_church_membership_id')->nullable()->constrained('church_memberships')->nullOnDelete();
            $table->timestamps();

            $table->index(['church_id', 'status'], 'church_domains_church_status_idx');
            $table->index(['status', 'last_checked_at'], 'church_domains_status_checked_idx');
            $table->index(['church_id', 'is_primary', 'status'], 'church_domains_church_primary_status_idx');
            $table->index(['released_at', 'normalized_hostname'], 'church_domains_release_host_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('church_domains');
    }
};
