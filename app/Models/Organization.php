<?php

namespace App\Models;

use App\Enums\OrganizationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Organization extends Model
{
    protected $fillable = ['name', 'slug', 'status'];

    protected function casts(): array
    {
        return ['status' => OrganizationStatus::class];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $organization) => $organization->uuid ??= (string) Str::uuid());
    }

    public function unitTypes(): HasMany
    {
        return $this->hasMany(OrganizationUnitType::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(OrganizationUnit::class);
    }

    public function rootUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'root_unit_id');
    }

    public function churchAssignments(): HasMany
    {
        return $this->hasMany(ChurchOrganizationAssignment::class);
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(OrganizationAuditEvent::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function communications(): HasMany
    {
        return $this->hasMany(OrganizationCommunication::class);
    }
}
