<?php

namespace App\Filament\Organization\Pages;

use App\Enums\ChurchOrganizationAssignmentStatus;
use App\Enums\OrganizationCapability;
use App\Filament\Organization\Concerns\InteractsWithOrganizationWorkspace;
use App\Models\Church;
use App\Models\ChurchOrganizationAssignment;
use App\Models\OrganizationUnit;
use App\Organizations\AuthorizedChurchAssignmentAction;
use App\Organizations\OrganizationScopeResolver;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;

class OrganizationChurches extends Page
{
    use InteractsWithOrganizationWorkspace;

    protected string $view = 'filament.organization.pages.churches';

    protected static ?string $title = 'Churches';

    protected static ?string $slug = 'churches';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home-modern';

    protected static string|\UnitEnum|null $navigationGroup = 'Structure';

    protected static ?int $navigationSort = 2;

    /** @return Collection<int, Church> */
    public function churches(): Collection
    {
        return app(OrganizationScopeResolver::class)
            ->churchesInScope()
            ->with('currentOrganizationAssignment.unit')
            ->orderBy('churches.name')
            ->get();
    }

    /** @return Collection<int, ChurchOrganizationAssignment> */
    public function relationshipHistory(): Collection
    {
        $unitIds = app(OrganizationScopeResolver::class)
            ->unitsInScope(OrganizationCapability::ChurchesView, includeArchived: true)
            ->select('organization_units.id');

        return ChurchOrganizationAssignment::query()
            ->with(['church', 'unit'])
            ->where('organization_id', $this->organization()->id)
            ->whereIn('organization_unit_id', $unitIds)
            ->where('status', '!=', ChurchOrganizationAssignmentStatus::ACTIVE->value)
            ->latest('requested_at')
            ->limit(20)
            ->get();
    }

    public function canManageAssignments(): bool
    {
        return app(OrganizationScopeResolver::class)
            ->unitsInScope(OrganizationCapability::ChurchesManageAssignments)
            ->exists();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('requestAttachment')
                ->label('Request attachment')
                ->icon('heroicon-o-link')
                ->visible(fn (): bool => $this->canManageAssignments())
                ->modalHeading('Request Church attachment')
                ->modalDescription('The Church remains independent until its active Primary accepts this request.')
                ->schema([
                    TextInput::make('church_slug')
                        ->label('Exact Church slug')
                        ->helperText('Enter the known Church slug. Keryon does not expose a directory of independent Churches here.')
                        ->required()
                        ->maxLength(255),
                    Select::make('unit_id')
                        ->label('Destination Unit')
                        ->options(fn (): array => $this->manageableUnitOptions())
                        ->required()
                        ->searchable(),
                ])
                ->action(function (array $data): void {
                    $church = Church::query()->where('slug', $data['church_slug'])->first();
                    $unit = OrganizationUnit::findOrFail($data['unit_id']);
                    if ($church === null) {
                        $this->runGoverned(fn () => throw new \DomainException('The Church could not be found or is unavailable for attachment.'), '');

                        return;
                    }
                    $this->runGoverned(
                        fn () => app(AuthorizedChurchAssignmentAction::class)->request(
                            auth()->user(),
                            $this->organization(),
                            $unit,
                            $church,
                        ),
                        'Attachment requested',
                    );
                }),
        ];
    }

    public function moveChurchAction(): Action
    {
        return Action::make('moveChurch')
            ->label('Move')
            ->icon('heroicon-o-arrows-right-left')
            ->schema([
                Select::make('destination_id')
                    ->label('Destination Unit')
                    ->options(fn (): array => $this->manageableUnitOptions())
                    ->required()
                    ->searchable(),
            ])
            ->action(function (array $data, array $arguments): void {
                $assignment = ChurchOrganizationAssignment::findOrFail($arguments['assignment']);
                $destination = OrganizationUnit::findOrFail($data['destination_id']);
                $this->runGoverned(
                    fn () => app(AuthorizedChurchAssignmentAction::class)->move(auth()->user(), $assignment, $destination),
                    'Church moved',
                );
            });
    }

    public function detachChurchAction(): Action
    {
        return Action::make('detachChurch')
            ->label('Detach')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Detach this Church?')
            ->modalDescription('Detaching ends Organization governance. It does not delete the Church, its memberships or any Church-owned records.')
            ->schema([
                TextInput::make('reason')->label('Reason')->maxLength(500),
            ])
            ->action(function (array $data, array $arguments): void {
                $assignment = ChurchOrganizationAssignment::findOrFail($arguments['assignment']);
                $this->runGoverned(
                    fn () => app(AuthorizedChurchAssignmentAction::class)->detach(
                        auth()->user(),
                        $assignment,
                        $data['reason'] ?: null,
                    ),
                    'Church detached',
                );
            });
    }

    /** @return array<int, string> */
    private function manageableUnitOptions(): array
    {
        return app(OrganizationScopeResolver::class)
            ->unitsInScope(OrganizationCapability::ChurchesManageAssignments)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
