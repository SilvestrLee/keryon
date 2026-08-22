<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Database\Factories\FaithFlowUsageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only operational observability record — see K-FAITHFLOW-001A
 * §22/§27/§34/§42 and K-FAITHFLOW-001B §22. Deliberately carries no
 * prompt/response text (see K-FAITHFLOW-001B §23/§53) and is never
 * user-editable — no update path is ever intended for this model.
 */
class FaithFlowUsage extends Model
{
    /** @use HasFactory<FaithFlowUsageFactory> */
    use HasFactory;
    use BelongsToChurch;

    protected $table = 'faithflow_usage';

    // Append-only — no updated_at column exists on this table.
    const UPDATED_AT = null;

    protected $fillable = [
        'operation',
        'provider',
        'model',
        'prompt_version',
        'input_tokens',
        'output_tokens',
        'latency_ms',
        'estimated_cost_cents',
        'status',
        'error_category',
    ];

    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'latency_ms' => 'integer',
            'estimated_cost_cents' => 'integer',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(FaithFlowRun::class, 'faithflow_run_id');
    }

    public function output(): BelongsTo
    {
        return $this->belongsTo(FaithFlowOutput::class, 'faithflow_output_id');
    }
}
