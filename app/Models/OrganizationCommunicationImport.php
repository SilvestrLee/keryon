<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

/**
 * K-ORG-COMMS-001E §30-§32 — the durable Church-side import event.
 * Written exactly once per Accepted delivery, exclusively through
 * `OrganizationCommunicationImportService`. Fully immutable after
 * creation — an import event is a historical fact, never edited.
 *
 * This is deliberately Church-evidence, not Organization-owned: it
 * records something a Church did, even though it references Organization
 * source IDs. It grants the Organization no ability to read or mutate
 * the resulting local Church records (§31/§56) — see
 * `OrganizationCommunicationImportResult` for the bounded lineage, and
 * `organization_id`/`imported_at` here for the one factual signal a
 * future K-ORG-COMMS-001F Organization-side view may read.
 */
class OrganizationCommunicationImport extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['imported_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $import): void {
            $import->uuid ??= (string) Str::uuid();
            $import->imported_at ??= now();
        });

        static::updating(function (): never {
            throw new LogicException('An Organization communication import event is immutable once recorded.');
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(OrganizationCommunicationDelivery::class, 'organization_communication_delivery_id');
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(OrganizationCommunicationRevision::class, 'organization_communication_revision_id');
    }

    public function importerMembership(): BelongsTo
    {
        return $this->belongsTo(ChurchMembership::class, 'imported_by_church_membership_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(OrganizationCommunicationImportResult::class);
    }
}
