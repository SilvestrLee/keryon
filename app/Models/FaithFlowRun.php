<?php

namespace App\Models;

use App\Enums\FaithFlowRunStatus;
use App\Models\Concerns\BelongsToChurch;
use Database\Factories\FaithFlowRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One source-material transformation workspace — see K-FAITHFLOW-001A §9/§10
 * and K-FAITHFLOW-001B §7/§8/§10. Persistence only: no provider calls, no
 * queue dispatch, no orchestration logic belongs here (see
 * K-FAITHFLOW-001B §46) — that lives in Action/Job classes from
 * K-FAITHFLOW-001C onward.
 */
class FaithFlowRun extends Model
{
    /** @use HasFactory<FaithFlowRunFactory> */
    use HasFactory;
    use BelongsToChurch;
    use SoftDeletes;

    protected $table = 'faithflow_runs';

    // church_id/created_by/status/canonical_analysis are server-managed, not
    // casually fillable — mirrors ContentItem's own posture. Tests use the
    // factory directly; a later Action class (K-FAITHFLOW-001C+) is the only
    // intended write path once analysis is implemented.
    protected $fillable = [
        'source_text',
        'source_char_count',
    ];

    protected function casts(): array
    {
        return [
            'status' => FaithFlowRunStatus::class,
            'canonical_analysis' => 'array',
            'analysis_attempts' => 'integer',
            'source_char_count' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function outputs(): HasMany
    {
        return $this->hasMany(FaithFlowOutput::class, 'faithflow_run_id');
    }

    public function usage(): HasMany
    {
        return $this->hasMany(FaithFlowUsage::class, 'faithflow_run_id');
    }
}
