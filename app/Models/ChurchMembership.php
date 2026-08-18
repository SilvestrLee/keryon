<?php

namespace App\Models;

use App\Enums\ChurchRole;
use App\Enums\MembershipStatus;
use App\Exceptions\CannotRemoveFinalPrimaryAdministratorException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The relationship between one User and one Church — access, status, and
 * one or more system-defined responsibility roles. Not a bare pivot.
 * See Keryon Blueprint v1.4.1 §4 and its companion engineering document.
 */
class ChurchMembership extends Model
{
    use HasFactory;

    protected $fillable = [
        'church_id',
        'user_id',
        'status',
        'is_primary',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => MembershipStatus::class,
            'is_primary' => 'boolean',
            'joined_at' => 'datetime',
        ];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(ChurchMembershipRole::class, 'church_membership_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', MembershipStatus::ACTIVE->value);
    }

    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }

    public function hasRole(ChurchRole $role): bool
    {
        return $this->roles()->where('role', $role->value)->exists();
    }

    /**
     * pluck('role') on this relation applies the ChurchMembershipRole
     * model's cast, so it returns ChurchRole instances, not raw strings —
     * ->map()->value maps it back to the list<string> this method
     * actually promises. Found and fixed during K-IDENTITY-001B-R4's ORM
     * integrity check.
     *
     * @return list<string>
     */
    public function roleValues(): array
    {
        return $this->roles()->pluck('role')->map(fn (ChurchRole $role) => $role->value)->all();
    }

    /**
     * @param  list<ChurchRole|string>  $roles
     */
    public function assignRoles(array $roles): void
    {
        foreach ($roles as $role) {
            $value = $role instanceof ChurchRole ? $role->value : $role;

            $this->roles()->firstOrCreate(['role' => $value]);
        }
    }

    /**
     * Create the first — and initially only — membership for a newly
     * created Church, wrapped in a transaction so a self-service Church
     * is never left ownerless if role assignment fails partway through.
     * See K-IDENTITY-001A §33.
     *
     * @param  list<ChurchRole|string>  $roles
     */
    public static function createPrimary(Church $church, User $user, array $roles = []): self
    {
        return DB::transaction(function () use ($church, $user, $roles) {
            $membership = static::create([
                'church_id' => $church->id,
                'user_id' => $user->id,
                'status' => MembershipStatus::ACTIVE,
                'is_primary' => true,
                'joined_at' => now(),
            ]);

            $membership->assignRoles($roles);

            return $membership;
        });
    }

    /**
     * Suspend this membership. Guards the Primary Administrator invariant —
     * see Blueprint v1.4.1 §7.
     */
    public function suspend(): void
    {
        $this->guardAgainstRemovingFinalPrimary();

        $this->status = MembershipStatus::SUSPENDED;
        $this->save();
    }

    /**
     * Remove this membership. Guards the Primary Administrator invariant —
     * see Blueprint v1.4.1 §7.
     */
    public function remove(): void
    {
        $this->guardAgainstRemovingFinalPrimary();

        $this->status = MembershipStatus::REMOVED;
        $this->save();
    }

    protected function guardAgainstRemovingFinalPrimary(): void
    {
        if (! $this->is_primary || $this->status !== MembershipStatus::ACTIVE) {
            return;
        }

        $anotherActivePrimaryExists = static::query()
            ->where('church_id', $this->church_id)
            ->where('id', '!=', $this->id)
            ->active()
            ->primary()
            ->exists();

        if (! $anotherActivePrimaryExists) {
            throw new CannotRemoveFinalPrimaryAdministratorException($this->church_id);
        }
    }

    /**
     * Transfer Primary Administrator status atomically within the same
     * church, leaving exactly one active Primary at all times.
     * See Blueprint v1.4.1 §7.
     */
    public static function transferPrimary(self $from, self $to): void
    {
        if ($from->church_id !== $to->church_id) {
            throw new InvalidArgumentException('Primary Administrator transfer must occur within the same church.');
        }

        if ($to->status !== MembershipStatus::ACTIVE) {
            throw new InvalidArgumentException('Cannot transfer Primary Administrator status to a membership that is not active.');
        }

        DB::transaction(function () use ($from, $to): void {
            $from->is_primary = false;
            $from->save();

            $to->is_primary = true;
            $to->save();
        });
    }
}
