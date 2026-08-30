<?php

namespace App\Filament\Pages;

use App\ChurchStaff\InviteChurchStaff;
use App\ChurchStaff\ReactivateChurchStaff;
use App\ChurchStaff\RemoveChurchStaff;
use App\ChurchStaff\ResendChurchStaffInvitation;
use App\ChurchStaff\RevokeChurchStaffInvitation;
use App\ChurchStaff\SuspendChurchStaff;
use App\ChurchStaff\TransferPrimaryAdministrator;
use App\ChurchStaff\UpdateChurchStaffRoles;
use App\Enums\Capability;
use App\Enums\ChurchRole;
use App\Enums\ChurchStaffInvitationStatus;
use App\Enums\MembershipStatus;
use App\Models\ChurchMembership;
use App\Models\ChurchStaffInvitation;
use App\Support\TenantContext;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ChurchStaffAccess extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Staff & Access';

    protected static ?string $title = 'Staff & Access';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.church-staff-access';

    public string $inviteEmail = '';

    public array $inviteRoles = [];

    public array $rolesFor = [];

    public ?string $deliveryUrl = null;

    public static function canAccess(): bool
    {
        return app(TenantContext::class)->currentMembership()?->hasCapability(Capability::StaffView) ?? false;
    }

    public function mount(): void
    {
        Gate::authorize('viewAny', ChurchMembership::class);
        foreach ($this->memberships() as $membership) {
            $this->rolesFor[$membership->id] = $membership->roleValues();
        }
    }

    public function invite(InviteChurchStaff $action): void
    {
        abort_unless($this->canPrepareLocalInvitation(), 403);
        Gate::authorize('create', [ChurchStaffInvitation::class, $this->actor()->church]);
        $data = $this->validate(['inviteEmail' => ['required', 'email', 'max:255'], 'inviteRoles' => ['required', 'array', 'min:1'], 'inviteRoles.*' => ['required', 'in:'.implode(',', array_column(ChurchRole::cases(), 'value'))]]);
        $this->run(function () use ($action, $data): void {
            $result = $action->execute($this->actor(), $data['inviteEmail'], $data['inviteRoles'], (string) Str::uuid());
            $this->deliveryUrl = $result->route();
            $this->reset(['inviteEmail', 'inviteRoles']);
        }, 'Staff invitation prepared');
    }

    public function resend(int $id, ResendChurchStaffInvitation $action): void
    {
        $invite = ChurchStaffInvitation::findOrFail($id);
        Gate::authorize('update', $invite);
        $this->run(function () use ($action, $invite): void {
            $this->deliveryUrl = $action->execute($this->actor(), $invite)->route();
        }, 'Invitation link rotated');
    }

    public function revoke(int $id, RevokeChurchStaffInvitation $action): void
    {
        $invite = ChurchStaffInvitation::findOrFail($id);
        Gate::authorize('update', $invite);
        $this->run(fn () => $action->execute($this->actor(), $invite), 'Invitation revoked');
    }

    public function updateRoles(int $id, UpdateChurchStaffRoles $action): void
    {
        $target = ChurchMembership::findOrFail($id);
        Gate::authorize('update', $target);
        $this->run(fn () => $action->execute($this->actor(), $target, $this->rolesFor[$id] ?? []), 'Staff roles updated');
    }

    public function suspend(int $id, SuspendChurchStaff $action): void
    {
        $target = ChurchMembership::findOrFail($id);
        Gate::authorize('suspend', $target);
        $this->run(fn () => $action->execute($this->actor(), $target), 'Staff access suspended');
    }

    public function reactivate(int $id, ReactivateChurchStaff $action): void
    {
        $target = ChurchMembership::findOrFail($id);
        Gate::authorize('reactivate', $target);
        $this->run(fn () => $action->execute($this->actor(), $target, $this->rolesFor[$id] ?? $target->roleValues()), 'Staff access reactivated');
    }

    public function remove(int $id, RemoveChurchStaff $action): void
    {
        $target = ChurchMembership::findOrFail($id);
        Gate::authorize('remove', $target);
        $this->run(fn () => $action->execute($this->actor(), $target), 'Staff membership removed');
    }

    public function transferPrimary(int $id, TransferPrimaryAdministrator $action): void
    {
        $target = ChurchMembership::findOrFail($id);
        $this->run(fn () => $action->execute($this->actor(), $target), 'Primary Administrator transferred');
    }

    public function memberships()
    {
        return ChurchMembership::query()->where('church_id', $this->actor()->church_id)->with(['user', 'roles'])->orderByRaw("case status when 'active' then 0 when 'suspended' then 1 else 2 end")->orderBy('id')->get();
    }

    public function pendingInvitations()
    {
        return ChurchStaffInvitation::query()->where('church_id', $this->actor()->church_id)->where('status', ChurchStaffInvitationStatus::PENDING)->with('roles')->latest()->get();
    }

    public function canManage(): bool
    {
        return $this->actor()->hasCapability(Capability::StaffManage);
    }

    public function canPrepareLocalInvitation(): bool
    {
        return $this->canManage() && (app()->environment('local', 'testing') || (bool) config('staff.local_invitation_surface'));
    }

    public function roleOptions(): array
    {
        return ChurchRole::options();
    }

    public function statuses(): string
    {
        return MembershipStatus::class;
    }

    private function actor(): ChurchMembership
    {
        return app(TenantContext::class)->currentMembership();
    }

    private function run(callable $callback, string $success): void
    {
        try {
            $callback();
            Notification::make()->success()->title($success)->send();
        } catch (DomainException $exception) {
            Notification::make()->danger()->title('Access change unavailable')->body($exception->getMessage())->send();
        }
    }
}
