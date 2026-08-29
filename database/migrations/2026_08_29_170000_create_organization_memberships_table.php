<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('invited');
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'user_id'], 'org_memberships_org_user_unique');
            $table->index(['user_id', 'status'], 'org_memberships_user_status_idx');
            $table->index(['organization_id', 'status'], 'org_memberships_org_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_memberships');
    }
};
