<?php

namespace App\Models;

use App\Enums\PolicyAudience;
use App\Enums\PolicyDocumentType;
use App\Enums\PolicyVersionStatus;
use App\Trust\Legal\ReservedSyntheticPolicyIdentifiers;
use DomainException;
use Illuminate\Database\Eloquent\Model;

/**
 * K-LEGAL-001B-B1A — schema and integrity foundation only. Draft creation
 * and editing are safe; the lifecycle beyond draft is NOT implemented here.
 *
 * approve()/publish()/retire() (K-LEGAL-001B-A-ARCHITECTURE.md §4.2, as
 * corrected by K-LEGAL-001B-A-DECISION-ADDENDUM.md §4) do not exist yet —
 * they require the separately approved HTML sanitizer and a verified
 * platform-operator authorization context, neither of which this milestone
 * builds (see K-LEGAL-001B-B1A-DEPENDENCY-REVIEW.md). Consequently, every
 * row this milestone can produce is permanently `draft`: the updating()
 * guard below unconditionally rejects any direct assignment touching
 * `status`, `published_at`, or any approval/retirement metadata — there is
 * no legitimate path to any other state yet, by design, not merely by
 * omission. A future milestone that builds the three transition methods
 * will REPLACE that unconditional rejection with the exact three permitted
 * transition shapes already fully specified in Architecture §4.4; it will
 * not need to change the separate, already-forward-correct guard below that
 * freezes identity/content/title/effective_at once a row leaves draft.
 *
 * This model is platform-owned, cross-tenant governance data — no
 * BelongsToChurch, no church_id scoping, no tenant Filament exposure
 * (Architecture §13).
 */
class PolicyVersion extends Model
{
    protected $fillable = [
        'document_type', 'audience', 'version', 'title', 'content', 'effective_at', 'is_synthetic_fixture',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => PolicyDocumentType::class,
            'audience' => PolicyAudience::class,
            'status' => PolicyVersionStatus::class,
            'is_synthetic_fixture' => 'boolean',
            'effective_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'retired_at' => 'immutable_datetime',
        ];
    }

    /**
     * Convenience builder — all validation lives in the creating() guard
     * below, not here, so any creation path is protected, not only this one.
     */
    public static function createDraft(
        PolicyDocumentType $documentType,
        string $version,
        string $content = '',
        string $title = '',
        ?PolicyAudience $audience = null,
        bool $isSyntheticFixture = false,
    ): self {
        return static::create([
            'document_type' => $documentType->value,
            'audience' => $audience?->value,
            'version' => $version,
            'title' => $title,
            'content' => $content,
            'is_synthetic_fixture' => $isSyntheticFixture,
        ]);
    }

    protected static function booted(): void
    {
        static::saving(function (self $version): void {
            // sha256 is always system-derived from `content`, never
            // caller-supplied — meaningful for a draft (reflects the current,
            // unsanitized working text) and re-derived from the SANITIZED
            // content by the future approve() method, which will overwrite
            // both content and sha256 together in the same write.
            //
            // saving() fires BEFORE creating() in Eloquent's event order.
            // During a create(), $version->exists is still false at this
            // point and creating() has not yet forced status back to draft —
            // gating this recompute on the CURRENT status value would let a
            // caller who sets status/sha256 directly (forceCreate(), or
            // direct property assignment bypassing $fillable on a new,
            // unsaved instance) skip the recompute entirely, persisting a
            // forged hash underneath a status creating() then silently
            // corrects back to draft. For a brand-new record there is no
            // legitimate non-draft status yet, so the recompute must be
            // unconditional here; only an UPDATE to an already-persisted,
            // still-draft row is gated on the current status.
            if (! $version->exists) {
                $version->sha256 = hash('sha256', (string) $version->content);

                return;
            }
            $status = $version->status instanceof PolicyVersionStatus ? $version->status->value : $version->status;
            if ($status === PolicyVersionStatus::DRAFT->value) {
                $version->sha256 = hash('sha256', (string) $version->content);
            }
        });

        static::creating(function (self $version): void {
            // A PolicyVersion may only ever be BORN a draft, with a clean
            // lifecycle slate — there is no legitimate way yet for a row to
            // come into existence in any other state.
            $version->status = PolicyVersionStatus::DRAFT->value;
            $version->is_synthetic_fixture = (bool) ($version->is_synthetic_fixture ?? false);
            $version->published_at = null;
            $version->approved_by_reference = null;
            $version->approved_at = null;
            $version->approval_evidence_reference = null;
            $version->retired_at = null;

            self::assertValidIdentity($version);
            self::assertReservedIdentifierConsistency($version);
        });

        static::updating(function (self $version): void {
            $lifecycleFields = ['status', 'published_at', 'approved_by_reference', 'approved_at', 'approval_evidence_reference', 'retired_at'];
            if ($version->isDirty($lifecycleFields)) {
                throw new DomainException(
                    'PolicyVersion lifecycle transitions are not implemented in this milestone; '
                    .'no direct assignment toward approved, published, or retired is permitted.'
                );
            }

            $originalStatus = PolicyVersionStatus::tryFrom((string) $version->getRawOriginal('status'));
            if ($originalStatus !== PolicyVersionStatus::DRAFT) {
                $protectedOnceNonDraft = ['document_type', 'audience', 'version', 'title', 'effective_at', 'content', 'sha256', 'is_synthetic_fixture'];
                if ($version->isDirty($protectedOnceNonDraft)) {
                    throw new DomainException('A PolicyVersion\'s identity, content, and title/effective_at metadata are immutable once approved.');
                }
            }

            self::assertValidIdentity($version);
            self::assertReservedIdentifierConsistency($version);
        });

        static::deleting(function (self $version): void {
            // Authorize by the PERSISTED status (getRawOriginal(), the value
            // as it exists in the database), never the in-memory $version
            // ->status attribute — a caller could set ->status = 'draft' on
            // an already-persisted, genuinely approved row without saving
            // that change, then call delete(); checking the in-memory value
            // would incorrectly authorize deleting a real approved record.
            $originalStatus = PolicyVersionStatus::tryFrom((string) $version->getRawOriginal('status'));
            if ($originalStatus !== PolicyVersionStatus::DRAFT) {
                throw new DomainException('Only a draft PolicyVersion may be deleted.');
            }
        });
    }

    private static function assertValidIdentity(self $version): void
    {
        $documentType = $version->document_type instanceof PolicyDocumentType ? $version->document_type->value : $version->document_type;
        if (blank($documentType) || PolicyDocumentType::tryFrom((string) $documentType) === null) {
            throw new DomainException('PolicyVersion requires a valid document_type.');
        }

        if ($version->audience !== null) {
            $audience = $version->audience instanceof PolicyAudience ? $version->audience->value : $version->audience;
            if (PolicyAudience::tryFrom((string) $audience) === null) {
                throw new DomainException('PolicyVersion audience, if set, must be a valid value.');
            }
        }

        if (blank($version->version)) {
            throw new DomainException('PolicyVersion requires a non-blank version identifier.');
        }
        if (strlen((string) $version->version) > 100) {
            throw new DomainException('PolicyVersion version identifier must not exceed 100 characters.');
        }
    }

    private static function assertReservedIdentifierConsistency(self $version): void
    {
        $isReserved = in_array($version->version, ReservedSyntheticPolicyIdentifiers::IDENTIFIERS, true);
        if ($isReserved && ! ((bool) $version->is_synthetic_fixture)) {
            throw new DomainException('This version identifier is reserved for synthetic fixtures and cannot be registered as a non-synthetic policy.');
        }
    }
}
