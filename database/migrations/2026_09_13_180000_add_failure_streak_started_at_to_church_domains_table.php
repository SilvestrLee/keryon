<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('church_domains', function (Blueprint $table): void {
            // Anchors the current confirmed-failure streak, independent of
            // last_checked_at (which moves on every check, including the
            // failing one, and so cannot represent when the streak began).
            // See K-DOMAIN-001E §2.
            $table->timestamp('failure_streak_started_at')->nullable()->after('consecutive_failures');

            // Provisioning-poll due-selection filters on tls_status alone
            // (church_domains_status_checked_idx already covers the
            // health-check due-selection query on (status, last_checked_at)).
            $table->index('tls_status', 'church_domains_tls_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('church_domains', function (Blueprint $table): void {
            $table->dropIndex('church_domains_tls_status_idx');
            $table->dropColumn('failure_streak_started_at');
        });
    }
};
