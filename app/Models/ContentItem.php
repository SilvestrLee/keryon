<?php

namespace App\Models;

use App\Enums\ContentOrigin;
use App\Enums\ContentStatus;
use App\Enums\ContentType;
use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use LogicException;

class ContentItem extends Model
{
    use BelongsToChurch;
    use SoftDeletes;

    protected $fillable = [
        'title',
        'content_type',
        'body',
    ];

    protected function casts(): array
    {
        return [
            'content_type' => ContentType::class,
            'status' => ContentStatus::class,
            'origin' => ContentOrigin::class,
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (Auth::check()) {
                $model->created_by ??= Auth::id();
                $model->updated_by ??= Auth::id();
            }

            // The schema-level defaults exist for direct DB inserts, but
            // Eloquent does not read DB-applied column defaults back into
            // the in-memory model after create() — set them explicitly
            // here so the model reflects reality immediately.
            $model->status ??= ContentStatus::DRAFT;
            $model->origin ??= ContentOrigin::HUMAN;
        });

        static::updating(function (self $model): void {
            $contentFieldsChanged = $model->isDirty(['title', 'content_type', 'body']);

            if (Auth::check() && $contentFieldsChanged) {
                $model->updated_by = Auth::id();
            }

            // A substantive edit to approved content invalidates its
            // approval — K-CONTENT-002 §19. Only relevant content fields
            // trigger this; framework-managed timestamps do not. Skipped
            // when the caller (a lifecycle method below) is already
            // managing `status` explicitly in this same save.
            $originalStatus = $model->getOriginal('status');
            $originalStatusValue = $originalStatus instanceof ContentStatus
                ? $originalStatus->value
                : $originalStatus;

            if (
                $originalStatusValue === ContentStatus::APPROVED->value
                && $contentFieldsChanged
                && ! $model->isDirty('status')
            ) {
                $model->status = ContentStatus::DRAFT;
                $model->approved_by = null;
                $model->approved_at = null;
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function campaignCommunications(): HasMany
    {
        return $this->hasMany(CampaignCommunication::class);
    }

    public function designs(): HasMany
    {
        return $this->hasMany(Design::class);
    }

    /**
     * draft|rejected -> review. Resubmission clears stale feedback.
     * updated_by reflects the acting user, per K-CONTENT-002 §6 —
     * workflow actions are meaningful mutations, not just content edits.
     */
    public function submitForReview(User $user): void
    {
        if (! in_array($this->status, [ContentStatus::DRAFT, ContentStatus::REJECTED], true)) {
            throw new LogicException('Only draft or needs-changes content can be submitted for review.');
        }

        $this->status = ContentStatus::REVIEW;
        $this->rejection_reason = null;
        $this->updated_by = $user->id;
        $this->save();
    }

    /**
     * review -> approved. Self-approval is allowed for MVP — see
     * K-CONTENT-002 §20 (no reviewer-role separation exists yet).
     * approved_by is independently authoritative for the approval act
     * itself; updated_by is also set to the same actor per §4.
     */
    public function approve(User $user): void
    {
        if ($this->status !== ContentStatus::REVIEW) {
            throw new LogicException('Only content in review can be approved.');
        }

        $this->status = ContentStatus::APPROVED;
        $this->approved_by = $user->id;
        $this->approved_at = now();
        $this->updated_by = $user->id;
        $this->rejection_reason = null;
        $this->save();
    }

    /**
     * review -> rejected. A reason is mandatory. No separate
     * rejected_by/reviewed_by field — updated_by is sufficient latest-
     * actor attribution per §5; this remains not a historical audit log.
     */
    public function requestChanges(User $user, string $reason): void
    {
        if ($this->status !== ContentStatus::REVIEW) {
            throw new LogicException('Only content in review can have changes requested.');
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('A reason is required to request changes.');
        }

        $this->status = ContentStatus::REJECTED;
        $this->rejection_reason = $reason;
        $this->updated_by = $user->id;
        $this->save();
    }

    /**
     * rejected -> draft. Feedback remains visible during revision; it is
     * only cleared on the next submitForReview() call.
     */
    public function returnToDraft(User $user): void
    {
        if ($this->status !== ContentStatus::REJECTED) {
            throw new LogicException('Only content marked as needing changes can be returned to draft.');
        }

        $this->status = ContentStatus::DRAFT;
        $this->updated_by = $user->id;
        $this->save();
    }
}
