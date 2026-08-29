<?php

namespace App\Models;

use App\Enums\OrganizationRole;
use App\Enums\OrganizationRoleAssignmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationRoleAssignment extends Model
{
    protected $fillable = [
        'organization_membership_id', 'organization_unit_id', 'role', 'status',
        'assigned_at', 'suspended_at', 'removed_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => OrganizationRole::class,
            'status' => OrganizationRoleAssignmentStatus::class,
            'assigned_at' => 'datetime',
            'suspended_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(OrganizationMembership::class, 'organization_membership_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'organization_unit_id');
    }
}
