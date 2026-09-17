<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\OrganizationStatus;
use App\Platform\Security\PlatformMfaCredentialService;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use SensitiveParameter;

#[Fillable(['name', 'email', 'password', 'church_id', 'locale'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Legacy identity relationship — church_id is a deprecated
     * compatibility bridge during the K-IDENTITY-001 transition.
     * memberships() is the authoritative church/user relationship.
     * See Keryon Blueprint v1.4.1 §3, §11.
     */
    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ChurchMembership::class);
    }

    public function activeMemberships(): HasMany
    {
        return $this->memberships()->active();
    }

    public function organizationMemberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function activeOrganizationMemberships(): HasMany
    {
        return $this->organizationMemberships()->active();
    }

    public function platformMembership(): HasOne
    {
        return $this->hasOne(PlatformMembership::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => $this->activeMemberships()
                ->whereHas('church', fn ($query) => $query->where('is_active', true))
                ->exists(),
            'organization' => $this->activeOrganizationMemberships()
                ->whereHas('organization', fn ($query) => $query->where('status', OrganizationStatus::ACTIVE->value))
                ->exists(),
            'central' => $this->platformMembership()->active()->exists(),
            default => false,
        };
    }

    public function getAppAuthenticationSecret(): ?string
    {
        $membership = $this->platformMembership()->active()->with('mfaCredential')->first();

        return $membership ? app(PlatformMfaCredentialService::class)->secretFor($membership) : null;
    }

    public function saveAppAuthenticationSecret(#[SensitiveParameter] ?string $secret): void
    {
        $membership = $this->platformMembership()->active()->firstOrFail();
        abort_if($secret === null, 403, 'Multi-factor authentication cannot be disabled from Central.');
        app(PlatformMfaCredentialService::class)->beginEnrollment($membership, $secret);
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->platformMembership()->active()->with('mfaCredential')->first()?->mfaCredential?->recovery_code_hashes;
    }

    public function saveAppAuthenticationRecoveryCodes(#[SensitiveParameter] ?array $codes): void
    {
        abort_if($codes === null, 403, 'Recovery credentials cannot be disabled from Central.');
        $membership = $this->platformMembership()->active()->firstOrFail();
        app(PlatformMfaCredentialService::class)->saveRecoveryHashes($membership, $codes);
    }

    public function churchStaffInvitations(): HasMany
    {
        return $this->hasMany(ChurchStaffInvitation::class, 'prospective_user_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
