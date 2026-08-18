<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Church extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'email',
        'phone',
        'website',
        'timezone',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Legacy identity relationship — users.church_id is a deprecated
     * compatibility bridge during the K-IDENTITY-001 transition.
     * memberships() is the authoritative church/user relationship.
     * See Keryon Blueprint v1.4.1 §3, §11.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ChurchMembership::class);
    }

    public function congregationMembers(): HasMany
    {
        return $this->hasMany(CongregationMember::class);
    }

    public function prayerRequests(): HasMany
    {
        return $this->hasMany(PrayerRequest::class);
    }
}