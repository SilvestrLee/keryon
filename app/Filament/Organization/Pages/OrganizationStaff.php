<?php

namespace App\Filament\Organization\Pages;

use App\Enums\OrganizationCapability;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationRoleAssignmentStatus;
use App\Filament\Organization\Concerns\InteractsWithOrganizationWorkspace;
use App\Models\OrganizationMembership;
use App\Models\OrganizationRoleAssignment;
use App\Models\OrganizationUnit;
use App\Models\User;
use App\Organizations\OrganizationIdentityService;
use App\Organizations\OrganizationScopeResolver;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

class OrganizationStaff extends Page
{
    use InteractsWithOrganizationWorkspace;

    protected string $view = 'filament.organization.pages.staff';

    protected static ?string $title = 'People & Access';

    protected static ?string $slug = 'staff';

    protected static ?string $navigationLabel = 'People & Access';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|\UnitEnum|null $navigationGroup = 'People';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return parent::canAccess()
            && app(OrganizationScopeResolver::class)->hasRootCapability(OrganizationCapability::MembershipsView);
    }

    /** @return Collection<int, OrganizationMembership> */
    public function memberships(): Collection
    {
        Gate::authorize('viewAny', [OrganizationMembership::class, $this->organization()]);

        return OrganizationMembership::query()
            ->where('organization_id', $this->organization()->id)
            ->with(['user', 'roleAssignments.unit'])
            ->orderByRaw("case status when 'active' then 0 when 'invited' then 1 when 'suspended' then 2 else 3 end")
            ->orderBy('id')
            ->get();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('invite')
                ->label('Invite staff')
                ->icon('heroicon-o-user-plus')
                ->schema([
                    TextInput::make('email')
                        ->label('Existing Keryon user email')
                        ->email()
                        ->required()
                        ->maxLength(255),
                ])
                ->action(function (array $data): void {
                    Gate::authorize('create', [OrganizationMembership::class, $this->organization()]);
                    $user = User::query()->where('email', $data['email'])->first();
                    if ($user === null) {
                        $this->runGoverned(fn () => throw new \DomainException('No eligible Keryon user was found for that email.'), '');

                        return;
                    }
                    $this->runGoverned(
                        fn () => app(OrganizationIdentityService::class)->invite($this->organization(), $user, auth()->id()),
                        'Organization staff invitation created',
                    );
                }),
        ];
    }

    public function activateMembershipAction(): Action
    {
        return Action::make('activateMembership')
            ->label('Activate')
            ->requiresConfirmation()
            ->action(function (array $arguments): void {
                $membership = OrganizationMembership::findOrFail($arguments['membership']);
                Gate::authorize('update', $membership);
                $this->runGoverned(
                    fn () => app(OrganizationIdentityService::class)->activate($membership, auth()->id()),
                    'Organization membership activated',
                );
            });
    }

    public function suspendMembershipAction(): Action
    {
        return Action::make('suspendMembership')
            ->label('Suspend')
            ->color('warning')
            ->requiresConfirmation()
            ->action(function (array $arguments): void {
                $membership = OrganizationMembership::findOrFail($arguments['membership']);
                Gate::authorize('update', $membership);
                $this->runGoverned(
                    fn () => app(OrganizationIdentityService::class)->suspend($membership, auth()->id()),
                    'Organization membership suspended',
                );
            });
    }

    public function removeMembershipAction(): Action
    {
        return Action::make('removeMembership')
            ->label('Remove')
            ->color('danger')
            ->requiresConfirmation()
            ->action(function (array $arguments): void {
                $membership = OrganizationMembership::findOrFail($arguments['membership']);
                Gate::authorize('update', $membership);
                $this->runGoverned(
                    fn () => app(OrganizationIdentityService::class)->remove($membership, auth()->id()),
                    'Organization membership removed',
                );
            });
    }

    public function assignRoleAction(): Action
    {
        return Action::make('assignRole')
            ->label('Assign role')
            ->schema([
                Select::make('role')
                    ->options($this->roleOptions())
                    ->required()
                    ->live(),
                Select::make('unit_id')
                    ->label('Scope')
                    ->options(fn (): array => $this->activeUnitOptions())
                    ->helperText('Organization Administrator always uses the Organization root, regardless of this selection.')
                    ->required(fn ($get): bool => $get('role') !== OrganizationRole::ORGANIZATION_ADMINISTRATOR->value)
                    ->visible(fn ($get): bool => $get('role') !== OrganizationRole::ORGANIZATION_ADMINISTRATOR->value)
                    ->searchable(),
            ])
            ->action(function (array $data, array $arguments): void {
                $membership = OrganizationMembership::findOrFail($arguments['membership']);
                Gate::authorize('update', $membership);
                $role = OrganizationRole::from($data['role']);
                $unit = $role === OrganizationRole::ORGANIZATION_ADMINISTRATOR
                    ? $this->organization()->rootUnit
                    : OrganizationUnit::findOrFail($data['unit_id']);
                $this->runGoverned(
                    fn () => app(OrganizationIdentityService::class)->assignRole($membership, $role, $unit, auth()->id()),
                    'Organization role assigned',
                );
            });
    }

    public function changeRoleScopeAction(): Action
    {
        return Action::make('changeRoleScope')
            ->label('Change scope')
            ->schema([
                Select::make('unit_id')
                    ->label('New scope')
                    ->options(fn (): array => $this->activeUnitOptions())
                    ->required()
                    ->searchable(),
            ])
            ->action(function (array $data, array $arguments): void {
                $assignment = OrganizationRoleAssignment::findOrFail($arguments['assignment']);
                Gate::authorize('update', $assignment);
                $unit = OrganizationUnit::findOrFail($data['unit_id']);
                $this->runGoverned(
                    fn () => app(OrganizationIdentityService::class)->changeRoleScope($assignment, $unit, auth()->id()),
                    'Role scope changed',
                );
            });
    }

    public function removeRoleAction(): Action
    {
        return Action::make('removeRole')
            ->label('Remove role')
            ->color('danger')
            ->requiresConfirmation()
            ->action(function (array $arguments): void {
                $assignment = OrganizationRoleAssignment::findOrFail($arguments['assignment']);
                Gate::authorize('update', $assignment);
                $this->runGoverned(
                    fn () => app(OrganizationIdentityService::class)->removeRole($assignment, auth()->id()),
                    'Organization role removed',
                );
            });
    }

    /** @return array<string, string> */
    private function roleOptions(): array
    {
        return [
            OrganizationRole::ORGANIZATION_ADMINISTRATOR->value => OrganizationRole::ORGANIZATION_ADMINISTRATOR->label(),
            OrganizationRole::UNIT_ADMINISTRATOR->value => OrganizationRole::UNIT_ADMINISTRATOR->label(),
            OrganizationRole::ORGANIZATION_VIEWER->value => OrganizationRole::ORGANIZATION_VIEWER->label(),
        ];
    }

    /** @return array<int, string> */
    private function activeUnitOptions(): array
    {
        return OrganizationUnit::query()
            ->where('organization_id', $this->organization()->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function isActive(OrganizationMembership $membership): bool
    {
        return $membership->status === OrganizationMembershipStatus::ACTIVE;
    }

    public function roleIsActive(OrganizationRoleAssignment $assignment): bool
    {
        return $assignment->status === OrganizationRoleAssignmentStatus::ACTIVE;
    }
}
