<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique('platform_memberships_user_unique')->constrained('users')->restrictOnDelete();
            $table->string('role', 40);
            $table->string('status', 24);
            $table->foreignId('provisioned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at');
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'role', 'user_id'], 'platform_memberships_status_role_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_memberships');
    }
};
