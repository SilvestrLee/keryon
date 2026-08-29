<?php

namespace App\Models;

use App\Enums\OrganizationAuditEventType;
use App\Enums\OrganizationAuditSubjectType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationAuditEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'organization_id', 'event_type', 'subject_type', 'subject_id', 'church_id',
        'organization_unit_id', 'actor_user_id', 'previous_state', 'new_state', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => OrganizationAuditEventType::class,
            'subject_type' => OrganizationAuditSubjectType::class,
            'previous_state' => 'array',
            'new_state' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
