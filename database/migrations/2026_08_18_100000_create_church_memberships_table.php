<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive, Phase 1 of the K-IDENTITY-001B staged migration.
     * users.church_id is not touched by this migration and remains the
     * compatibility bridge until K-AUTH-001 converts all authorization
     * consumers off it. See Keryon_Identity_Membership_Authorization_
     * Authorization_Architecture_v1.4.1.md §11.
     */
    public function up(): void
    {
        Schema::create('church_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')
                ->constrained('churches')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                // A membership is the access grant itself, not attribution
                // metadata — it must not silently disappear when a User
                // row is deleted. Remove/suspend the membership first.
                // See Blueprint v1.4.1 preflight decision #7.
                ->restrictOnDelete();
            $table->string('status')->default('active');
            $table->boolean('is_primary')->default(false);
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['church_id', 'user_id']);
            $table->index(['church_id', 'status']);
            $table->index(['church_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('church_memberships');
    }
};
