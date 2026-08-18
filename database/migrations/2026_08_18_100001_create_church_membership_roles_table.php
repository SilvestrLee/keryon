<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('church_membership_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_membership_id')
                ->constrained('church_memberships')
                ->cascadeOnDelete();
            $table->string('role');
            $table->timestamps();

            $table->unique(['church_membership_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('church_membership_roles');
    }
};
