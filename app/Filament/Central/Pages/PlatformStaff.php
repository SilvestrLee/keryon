<?php

namespace App\Filament\Central\Pages;

use App\Enums\PlatformAuditReasonCategory;
use App\Enums\PlatformCapability;
use App\Enums\PlatformRole;
use App\Filament\Central\Concerns\InteractsWithPlatformWorkspace;
use App\Models\PlatformMembership;
use App\Models\User;
use App\Platform\PlatformStaffService;
use App\Platform\Security\PlatformMfaCredentialService;
use App\Support\PlatformContext;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;

class PlatformStaff extends Page
{
    use InteractsWithPlatformWorkspace;

    protected string $view = 'filament.central.pages.staff';

    protected static ?string $title = 'Platform Staff';

    protected static ?string $navigationLabel = 'Platform Staff';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?int $navigationSort = 20;

    public string $email = '';

    public string $role = '';

    public string $password = '';

    public string $reason = 'platform_administration';

    public string $note = '';

    public ?int $targetId = null;

    public string $targetIdentifier = '';

    public string $targetAction = '';

    protected static function requiredCapability(): PlatformCapability
    {
        return PlatformCapability::PlatformStaffView;
    }

    public function memberships(): Collection
    {
        abort_unless(auth()->user()->can('viewAny', PlatformMembership::class), 403);

        return PlatformMembership::query()->with(['user:id,name,email,email_verified_at', 'mfaCredential'])->orderBy('id')->get();
    }

    /** @return array<string,string> */
    public function roleOptions(): array
    {
        return collect(PlatformRole::cases())->mapWithKeys(fn (PlatformRole $role) => [$role->value => $role->label()])->all();
    }

    public function add(PlatformStaffService $staff): void
    {
        abort_unless(auth()->user()->can('create', PlatformMembership::class), 403);
        $data = $this->validate([
            'email' => ['required', 'email'],
            'role' => ['required', Rule::enum(PlatformRole::class)],
            'password' => ['required', 'string'],
            'reason' => ['required', Rule::enum(PlatformAuditReasonCategory::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $this->verifyPassword($data['password']);
        $user = User::query()->whereRaw('lower(email) = ?', [strtolower($data['email'])])->firstOrFail();
        $staff->create($user, PlatformRole::from($data['role']), $this->freshActor(), PlatformAuditReasonCategory::from($data['reason']), $data['note']);
        $this->reset(['email', 'role', 'password', 'note']);
        Notification::make()->success()->title('Platform access granted')->send();
    }

    public function prepareAction(int $membership, string $action): void
    {
        abort_unless(in_array($action, ['suspend', 'remove', 'reset-mfa'], true), 404);
        $target = PlatformMembership::query()->with('user:id,email')->findOrFail($membership);
        if ($action === 'reset-mfa') {
            abort_unless($this->platformMembership()->hasCapability(PlatformCapability::PlatformMfaReset), 403);
        } else {
            abort_unless(auth()->user()->can('update', $target), 403);
        }
        $this->targetId = $target->id;
        $this->targetIdentifier = '';
        $this->targetAction = $action;
        $this->password = '';
        $this->note = '';
    }

    public function confirmAction(PlatformStaffService $staff, PlatformMfaCredentialService $mfa): void
    {
        $target = PlatformMembership::query()->with('user:id,email')->findOrFail($this->targetId);
        if ($this->targetAction === 'reset-mfa') {
            abort_unless($this->platformMembership()->hasCapability(PlatformCapability::PlatformMfaReset), 403);
        } else {
            abort_unless(auth()->user()->can('update', $target), 403);
        }
        $data = $this->validate([
            'targetIdentifier' => ['required', 'string'],
            'password' => ['required', 'string'],
            'reason' => ['required', Rule::enum(PlatformAuditReasonCategory::class)],
            'note' => ['required', 'string', 'max:500'],
        ]);
        abort_unless(hash_equals(strtolower($target->user->email), strtolower(trim($data['targetIdentifier']))), 422, 'Type the target email exactly.');
        $this->verifyPassword($data['password']);
        $actor = $this->freshActor();
        if ($this->targetAction === 'reset-mfa') {
            $mfa->reset($target, $actor, $data['password'], PlatformAuditReasonCategory::from($data['reason']), $data['note']);
        } elseif ($this->targetAction === 'suspend') {
            $staff->suspend($target, $actor, PlatformAuditReasonCategory::from($data['reason']), $data['note']);
        } else {
            $staff->remove($target, $actor, PlatformAuditReasonCategory::from($data['reason']), $data['note']);
        }
        $this->reset(['targetId', 'targetIdentifier', 'targetAction', 'password', 'note']);
        app(PlatformContext::class)->forgetResolved();
        Notification::make()->success()->title('Platform access updated')->send();
    }

    private function freshActor(): PlatformMembership
    {
        app(PlatformContext::class)->forgetResolved();

        return $this->platformMembership();
    }

    private function verifyPassword(string $password): void
    {
        $key = 'central-reauth:'.auth()->id().':'.request()->ip();
        abort_if(RateLimiter::tooManyAttempts($key, 5), 429, 'Too many reauthorization attempts. Try again later.');
        if (! Hash::check($password, auth()->user()->password)) {
            RateLimiter::hit($key, 60);
            abort(403, 'Password confirmation failed.');
        }
        RateLimiter::clear($key);
    }
}
