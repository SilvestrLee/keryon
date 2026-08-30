<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commercial_audit_events', function (Blueprint $table): void {
            $table->foreignId('invoice_id')->nullable()->after('subscription_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->after('invoice_id')->constrained()->restrictOnDelete();
            $table->index(['invoice_id', 'occurred_at'], 'commercial_audit_invoice_idx');
            $table->index(['payment_id', 'occurred_at'], 'commercial_audit_payment_idx');
        });
    }

    public function down(): void
    {
        Schema::table('commercial_audit_events', function (Blueprint $table): void {
            $table->dropForeign(['invoice_id']);
            $table->dropForeign(['payment_id']);
            $table->dropIndex('commercial_audit_invoice_idx');
            $table->dropIndex('commercial_audit_payment_idx');
            $table->dropColumn(['invoice_id', 'payment_id']);
        });
    }
};
