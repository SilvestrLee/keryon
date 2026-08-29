<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationUnitPath extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $fillable = ['organization_id', 'ancestor_id', 'descendant_id', 'depth'];

    protected function casts(): array
    {
        return ['depth' => 'integer'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function ancestor(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'ancestor_id');
    }

    public function descendant(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'descendant_id');
    }
}
