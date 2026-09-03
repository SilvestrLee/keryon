<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_communication_revisions', function (Blueprint $table): void {
            $table->id();
            // Explicit short constraint names throughout this table: the
            // auto-generated `{table}_{column}_foreign` names exceed
            // MySQL's 64-char identifier limit for every column below.
            $table->foreignId('organization_communication_id')
                ->constrained('organization_communications', 'id', 'org_comm_revisions_communication_fk')
                ->cascadeOnDelete();
            $table->foreignId('created_by_organization_membership_id')
                ->constrained('organization_memberships', 'id', 'org_comm_revisions_creator_fk')
                ->restrictOnDelete();
            $table->foreignId('submitted_by_organization_membership_id')
                ->nullable()
                ->constrained('organization_memberships', 'id', 'org_comm_revisions_submitter_fk')
                ->restrictOnDelete();
            $table->foreignId('reviewed_by_organization_membership_id')
                ->nullable()
                ->constrained('organization_memberships', 'id', 'org_comm_revisions_reviewer_fk')
                ->restrictOnDelete();
            $table->foreignId('approved_by_organization_membership_id')
                ->nullable()
                ->constrained('organization_memberships', 'id', 'org_comm_revisions_approver_fk')
                ->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('state', 32)->default('draft');
            $table->string('title');
            $table->text('summary')->nullable();
            $table->text('requested_action')->nullable();
            $table->string('adaptation_policy', 48)->default('local_adaptation_encouraged');
            $table->date('campaign_starts_on')->nullable();
            $table->date('campaign_ends_on')->nullable();
            $table->date('recommended_response_on')->nullable();
            $table->date('suggested_publish_by')->nullable();
            $table->dateTime('available_from')->nullable();
            $table->dateTime('available_until')->nullable();
            $table->text('review_feedback')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('changes_requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_communication_id', 'version'], 'org_comm_revisions_version_unique');
            $table->index(['organization_communication_id', 'state'], 'org_comm_revisions_state_idx');
            $table->index(['state', 'submitted_at'], 'org_comm_revisions_review_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_communication_revisions');
    }
};
