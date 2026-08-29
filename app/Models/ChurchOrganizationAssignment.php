<?php

namespace App\Models;

use App\Enums\ChurchOrganizationAssignmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChurchOrganizationAssignment extends Model
{
    protected $fillable = [
        'church_id', 'organization_id', 'organization_unit_id', 'status',
        'requested_by', 'accepted_by', 'requested_at', 'effective_at', 'ended_at', 'reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => ChurchOrganizationAssignmentStatus::class,
            'requested_at' => 'datetime',
            'effective_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'organization_unit_id');
    }
}
