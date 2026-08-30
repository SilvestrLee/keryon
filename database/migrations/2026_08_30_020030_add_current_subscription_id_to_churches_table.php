<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('churches', function (Blueprint $table): void {
            $table->foreignId('current_subscription_id')->nullable()->after('current_organization_assignment_id')
                ->constrained('subscriptions')->restrictOnDelete();
            $table->index('current_subscription_id', 'churches_current_subscription_idx');
        });
    }

    public function down(): void
    {
        Schema::table('churches', function (Blueprint $table): void {
            $table->dropForeign(['current_subscription_id']);
            $table->dropIndex('churches_current_subscription_idx');
            $table->dropColumn('current_subscription_id');
        });
    }
};
