<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policy_governance_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_uuid')->unique('policy_governance_events_uuid_uq');

            // "Policy identity" is the natural key (document_type, version),
            // not a foreign key — PolicyVersion does not exist yet (this
            // milestone is deliberately isolated from it), and even once it
            // does, this keeps the same application-enforced-natural-key
            // philosophy already established for the acceptance tables. See
            // K-LEGAL-001B-A-DECISION-ADDENDUM.md §2.
            $table->string('document_type');
            $table->string('version', 100);

            $table->string('transition_type');
            $table->char('content_hash', 64);
            $table->string('actor_reference');
            $table->timestamp('occurred_at');

            $table->string('delivery_status')->default('pending');
            $table->unsignedInteger('delivery_attempts')->default(0);
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('external_acknowledgement_reference')->nullable();

            $table->timestamps();

            $table->index('delivery_status', 'policy_governance_events_delivery_status_idx');
            $table->index(['document_type', 'version'], 'policy_governance_events_document_version_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_governance_events');
    }
};
