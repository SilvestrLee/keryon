<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * K-ORG-COMMS-001B §34-§41 — the single authorized Organization
 * communication asset table. An asset belongs unambiguously to
 * Organization -> OrganizationCommunication -> Revision -> Asset. No
 * Church owner, no polymorphic owner, no generic Organization media
 * library. Rights basis, usage guidance, and attribution live directly
 * on this table (§40: smallest useful model) rather than a second
 * rights table, keeping this the one new table §36 authorizes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_communication_assets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            // Explicit short constraint names: MySQL's 64-char identifier
            // limit rejects the auto-generated `{table}_{column}_foreign`
            // name for every column below (K-ORG-COMMS-001B discovery —
            // the same defect existed, and was fixed, in the 001A tables).
            $table->foreignId('organization_communication_id')
                ->constrained('organization_communications', 'id', 'org_comm_assets_communication_fk')
                ->cascadeOnDelete();
            $table->foreignId('organization_communication_revision_id')
                ->constrained('organization_communication_revisions', 'id', 'org_comm_assets_revision_fk')
                ->cascadeOnDelete();
            $table->foreignId('uploaded_by_organization_membership_id')
                ->constrained('organization_memberships', 'id', 'org_comm_assets_uploader_fk')
                ->restrictOnDelete();

            $table->string('disk');
            $table->string('path');
            $table->string('original_filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->string('sha256', 64);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt_text')->nullable();

            $table->string('rights_basis', 48);
            $table->text('usage_guidance')->nullable();
            $table->boolean('attribution_required')->default(false);
            $table->string('attribution_text')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_communication_revision_id'], 'org_comm_assets_revision_idx');
            $table->index(['organization_id', 'created_at'], 'org_comm_assets_org_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_communication_assets');
    }
};
