<?php

namespace App\Filament\Organization\Pages;

use App\Enums\OrganizationCapability;
use App\Enums\OrganizationUnitStatus;
use App\Filament\Organization\Concerns\InteractsWithOrganizationWorkspace;
use App\Models\OrganizationUnit;
use App\Models\OrganizationUnitType;
use App\Organizations\OrganizationHierarchyService;
use App\Organizations\OrganizationScopeResolver;
use App\Organizations\Read\Dto\OrganizationUnitSummary;
use App\Organizations\Read\OrganizationWorkspaceQuery;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\WithPagination;

class OrganizationUnits extends Page
{
    use InteractsWithOrganizationWorkspace, WithPagination;

    protected string $view = 'filament.organization.pages.units';

    protected static ?string $title = 'Organization Structure';

    protected static ?string $slug = 'units';

    protected static ?string $navigationLabel = 'Structure';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static string|\UnitEnum|null $navigationGroup = 'Structure';

    protected static ?int $navigationSort = 1;

    public string $search = '';

    public string $state = OrganizationUnitStatus::ACTIVE->value;

    public function updatedSearch(): void
    {
        $this->resetPage('unitsPage');
    }

    public function updatedState(): void
    {
        $this->resetPage('unitsPage');
    }

    public function units(): LengthAwarePaginator
    {
        return app(OrganizationWorkspaceQuery::class)->paginateUnits($this->search, $this->state);
    }

    /** @return list<OrganizationUnitSummary> */
    public function scopeRoots(): array
    {
        return app(OrganizationWorkspaceQuery::class)->scopeRoots();
    }

    public function detailUrl(OrganizationUnitSummary $unit): string
    {
        return OrganizationUnitDetail::getUrl(['record' => $unit->id], panel: 'organization');
    }

    public function canManageUnits(): bool
    {
        return app(OrganizationScopeResolver::class)->unitsInScope(OrganizationCapability::UnitsManage)->exists();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createUnit')
                ->label('Create Unit')
                ->icon('heroicon-o-plus')
                ->visible(fn (): bool => $this->canManageUnits())
                ->modalHeading('Create organizational Unit')
                ->schema([
                    Select::make('parent_id')
                        ->label('Parent Unit')
                        ->options(fn (): array => $this->manageableUnitOptions())
                        ->required()
                        ->searchable(),
                    Select::make('type_id')
                        ->label('Unit type')
                        ->options(fn (): array => OrganizationUnitType::query()
                            ->where('organization_id', $this->organization()->id)
                            ->where('is_active', true)
                            ->orderBy('sort_order')
                            ->pluck('label', 'id')->all())
                        ->required(),
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('code')->required()->maxLength(255),
                ])
                ->action(function (array $data): void {
                    $parent = OrganizationUnit::findOrFail($data['parent_id']);
                    $type = OrganizationUnitType::findOrFail($data['type_id']);
                    Gate::authorize('create', [OrganizationUnit::class, $parent]);
                    $this->runGoverned(
                        fn () => app(OrganizationHierarchyService::class)->createUnit(
                            $this->organization(),
                            $type,
                            $parent,
                            ['name' => $data['name'], 'code' => $data['code']],
                            auth()->id(),
                        ),
                        'Unit created',
                    );
                }),
        ];
    }

    public function moveUnitAction(): Action
    {
        return Action::make('moveUnit')
            ->label('Move')
            ->icon('heroicon-o-arrows-right-left')
            ->schema(fn (array $arguments): array => [
                Select::make('destination_id')
                    ->label('New parent Unit')
                    ->options($this->manageableUnitOptions(exclude: (int) ($arguments['unit'] ?? 0)))
                    ->required()
                    ->searchable(),
            ])
            ->action(function (array $data, array $arguments): void {
                $unit = OrganizationUnit::findOrFail($arguments['unit']);
                $destination = OrganizationUnit::findOrFail($data['destination_id']);
                Gate::authorize('move', [$unit, $destination]);
                $this->runGoverned(
                    fn () => app(OrganizationHierarchyService::class)->moveUnit($unit, $destination, auth()->id()),
                    'Unit moved',
                );
            });
    }

    public function archiveUnitAction(): Action
    {
        return Action::make('archiveUnit')
            ->label('Archive')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Archived Units remain in Organization history but cannot receive new Churches, child Units or role assignments.')
            ->action(function (array $arguments): void {
                $unit = OrganizationUnit::findOrFail($arguments['unit']);
                Gate::authorize('archive', $unit);
                $this->runGoverned(
                    fn () => app(OrganizationHierarchyService::class)->archiveUnit($unit, auth()->id()),
                    'Unit archived',
                );
            });
    }

    /** @return array<int, string> */
    private function manageableUnitOptions(?int $exclude = null): array
    {
        return app(OrganizationScopeResolver::class)
            ->unitsInScope(OrganizationCapability::UnitsManage)
            ->when($exclude, fn ($query) => $query->whereKeyNot($exclude))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
