<?php

namespace App\Models;

use App\Enums\OrganizationUnitStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class OrganizationUnit extends Model
{
    protected $fillable = ['organization_id', 'organization_unit_type_id', 'parent_id', 'code', 'name', 'status'];

    protected function casts(): array
    {
        return ['status' => OrganizationUnitStatus::class];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $unit) => $unit->uuid ??= (string) Str::uuid());
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnitType::class, 'organization_unit_type_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function ancestorPaths(): HasMany
    {
        return $this->hasMany(OrganizationUnitPath::class, 'descendant_id');
    }

    public function descendantPaths(): HasMany
    {
        return $this->hasMany(OrganizationUnitPath::class, 'ancestor_id');
    }

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(OrganizationRoleAssignment::class);
    }
}
