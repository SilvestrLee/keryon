<?php

namespace App\Models;

use App\Enums\FaithFlowOutputStatus;
use App\Enums\FaithFlowOutputType;
use App\Models\Concerns\BelongsToChurch;
use Database\Factories\FaithFlowOutputFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One requested/generated output belonging to a FaithFlowRun — see
 * K-FAITHFLOW-001A §9-14/§19-21 and K-FAITHFLOW-001B §7/§11/§14/§26/§28.
 * Persistence + tiny invariant helpers only — no provider calls, no queue
 * dispatch (see K-FAITHFLOW-001B §46).
 */
class FaithFlowOutput extends Model
{
    /** @use HasFactory<FaithFlowOutputFactory> */
    use HasFactory;
    use BelongsToChurch;
    use SoftDeletes;

    protected $table = 'faithflow_outputs';

    protected $fillable = [
        'output_type',
    ];

    protected function casts(): array
    {
        return [
            'output_type' => FaithFlowOutputType::class,
            'status' => FaithFlowOutputStatus::class,
            'regeneration_count' => 'integer',
            'edited_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(FaithFlowRun::class, 'faithflow_run_id');
    }

    public function contentItem(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function usage(): HasMany
    {
        return $this->hasMany(FaithFlowUsage::class, 'faithflow_output_id');
    }

    /**
     * Whether a human has diverged from the last AI-generated text — see
     * K-FAITHFLOW-001A §20 and K-FAITHFLOW-001B §14.
     */
    public function isEdited(): bool
    {
        return $this->edited_at !== null;
    }

    /**
     * The state-machine guard K-FAITHFLOW-001D §27/§48 asks for: only a
     * GENERATED output has usable content to approve. PENDING/GENERATING
     * never had content; FAILED never successfully produced any (a failed
     * regeneration reverts to GENERATED rather than FAILED specifically so
     * this invariant — FAILED means "no usable content ever existed" —
     * stays true; see RegenerateFaithFlowOutput). The approval action
     * itself belongs to K-FAITHFLOW-001E; this is only the guard.
     */
    public function canBeApproved(): bool
    {
        return $this->status === FaithFlowOutputStatus::GENERATED;
    }
}
