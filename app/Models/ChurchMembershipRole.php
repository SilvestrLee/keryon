<?php

namespace App\Models;

use App\Enums\ChurchRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single responsibility role assigned to a ChurchMembership. Not a
 * first-class product concept in its own right — a plain Eloquent model
 * backing church_membership_roles so ChurchMembership::roles() can be a
 * normal Eloquent relation. See K-IDENTITY-001A §I / Blueprint v1.4.1 §8.
 */
class ChurchMembershipRole extends Model
{
    protected $fillable = [
        'role',
    ];

    protected function casts(): array
    {
        return [
            'role' => ChurchRole::class,
        ];
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(ChurchMembership::class, 'church_membership_id');
    }
}
