<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_version_entitlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_version_id')->constrained()->cascadeOnDelete();
            $table->string('entitlement_key');
            $table->string('value_type');
            $table->boolean('boolean_value')->nullable();
            $table->bigInteger('integer_value')->nullable();
            $table->timestamps();

            $table->unique(['plan_version_id', 'entitlement_key'], 'plan_entitlements_version_key_unique');
            $table->index(['entitlement_key', 'value_type'], 'plan_entitlements_key_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_version_entitlements');
    }
};
