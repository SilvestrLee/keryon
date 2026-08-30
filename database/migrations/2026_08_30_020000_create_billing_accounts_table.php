<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_accounts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('billing_accounts_uuid_unique');
            $table->string('name');
            $table->string('owner_type');
            $table->foreignId('church_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('organization_unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['owner_type', 'status'], 'billing_accounts_owner_status_idx');
            $table->index(['church_id', 'status'], 'billing_accounts_church_status_idx');
            $table->index(['organization_id', 'status'], 'billing_accounts_org_status_idx');
            $table->index(['organization_unit_id', 'status'], 'billing_accounts_unit_status_idx');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE billing_accounts ADD CONSTRAINT billing_accounts_one_owner_chk CHECK ((owner_type = 'church' AND church_id IS NOT NULL AND organization_id IS NULL AND organization_unit_id IS NULL) OR (owner_type = 'organization' AND church_id IS NULL AND organization_id IS NOT NULL AND organization_unit_id IS NULL) OR (owner_type = 'organization_unit' AND church_id IS NULL AND organization_id IS NULL AND organization_unit_id IS NOT NULL))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_accounts');
    }
};
