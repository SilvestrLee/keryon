<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('church_onboarding_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->unique('church_onboarding_church_uq')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->string('current_step');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->foreignId('last_actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'updated_at'], 'church_onboarding_status_updated_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('church_onboarding_states');
    }
};
