<?php

namespace App\Filament\Central\Pages;

use App\Enums\PlatformAuditReasonCategory;
use App\Enums\PlatformCapability;
use App\Filament\Central\Concerns\InteractsWithPlatformWorkspace;
use App\Models\PlatformMembership;
use App\Platform\Security\PlatformMfaCredentialService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Validation\Rule;

class MfaRecovery extends Page
{
    use InteractsWithPlatformWorkspace;

    protected string $view = 'filament.central.pages.mfa-recovery';

    protected static ?string $title = 'MFA Recovery';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?int $navigationSort = 31;

    public string $targetEmail = '';

    public string $password = '';

    public string $reason = 'security_response';

    public string $note = '';

    protected static function requiredCapability(): PlatformCapability
    {
        return PlatformCapability::PlatformMfaReset;
    }

    public function resetMfa(PlatformMfaCredentialService $service): void
    {
        $data = $this->validate(['targetEmail' => ['required', 'email'], 'password' => ['required'], 'reason' => ['required', Rule::enum(PlatformAuditReasonCategory::class)], 'note' => ['required', 'string', 'max:500']]);
        $target = PlatformMembership::query()->active()->whereHas('user', fn ($query) => $query->whereRaw('lower(email) = ?', [strtolower($data['targetEmail'])]))->with('mfaCredential')->firstOrFail();
        abort_unless($target->mfaCredential?->isUsable(), 409, 'The target does not have active MFA credentials.');
        $service->reset($target, $this->platformMembership(), $data['password'], PlatformAuditReasonCategory::from($data['reason']), $data['note']);
        $this->reset(['targetEmail', 'password', 'note']);
        Notification::make()->success()->title('MFA reset; re-enrollment required')->send();
    }
}
