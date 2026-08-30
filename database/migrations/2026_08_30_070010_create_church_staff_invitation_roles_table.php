<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('church_staff_invitation_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invitation_id')->constrained('church_staff_invitations')->cascadeOnDelete();
            $table->string('role');
            $table->timestamps();
            $table->unique(['invitation_id', 'role'], 'staff_invite_roles_invite_role_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('church_staff_invitation_roles');
    }
};
