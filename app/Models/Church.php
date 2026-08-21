<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        // K-CHURCHWEB-001B §22 — a physical address is an institutional
        // fact about the church, true whether or not a website exists;
        // it does not belong on Website Contact content. See the
        // K-CHURCHWEB-001B report §5.
        'address',
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

    /**
     * K-CHURCHWEB-001B §7 — the shared Brand Profile. Not Website-owned;
     * consumed by Website now and Design Studio/Campaigns later (§31).
     */
    public function brandProfile(): HasOne
    {
        return $this->hasOne(ChurchBrandProfile::class);
    }

    /**
     * K-CHURCHWEB-001B §24 — institutional, not Website-owned (§22 test).
     */
    public function socialLinks(): HasMany
    {
        return $this->hasMany(ChurchSocialLink::class);
    }

    /**
     * K-CHURCHWEB-001B §23 — institutional, not Website-owned (§22 test).
     */
    public function serviceTimes(): HasMany
    {
        return $this->hasMany(ChurchServiceTime::class);
    }

    /**
     * K-CHURCHWEB-001B §12 — institutional media, not Website-owned.
     */
    public function mediaAssets(): HasMany
    {
        return $this->hasMany(MediaAsset::class);
    }
}