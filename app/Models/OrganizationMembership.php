<?php

namespace App\Models;

use App\Enums\OrganizationCapability;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationRoleAssignmentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationMembership extends Model
{
    protected $fillable = [
        'organization_id', 'user_id', 'status', 'invited_at', 'joined_at',
        'suspended_at', 'removed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrganizationMembershipStatus::class,
            'invited_at' => 'datetime',
            'joined_at' => 'datetime',
            'suspended_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(OrganizationRoleAssignment::class);
    }

    public function createdCommunications(): HasMany
    {
        return $this->hasMany(OrganizationCommunication::class, 'created_by_organization_membership_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', OrganizationMembershipStatus::ACTIVE->value);
    }

    /**
     * Answers only whether an active assignment grants this capability
     * somewhere. Target authorization must additionally use the scope resolver.
     */
    public function hasCapability(OrganizationCapability $capability): bool
    {
        if ($this->status !== OrganizationMembershipStatus::ACTIVE) {
            return false;
        }

        return $this->roleAssignments()
            ->where('status', OrganizationRoleAssignmentStatus::ACTIVE->value)
            ->whereIn('role', OrganizationRole::valuesGranting($capability))
            ->exists();
    }
}
