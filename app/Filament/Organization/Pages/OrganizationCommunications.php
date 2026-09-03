<?php

namespace App\Filament\Organization\Pages;

use App\Enums\OrganizationCapability;
use App\Enums\OrganizationCommunicationAdaptationPolicy;
use App\Enums\OrganizationCommunicationKind;
use App\Filament\Organization\Concerns\InteractsWithOrganizationWorkspace;
use App\Models\OrganizationUnit;
use App\Organizations\Communications\OrganizationCommunicationManager;
use App\Organizations\Communications\Read\OrganizationCommunicationQuery;
use App\Organizations\OrganizationScopeResolver;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\WithPagination;

/**
 * K-ORG-COMMS-001B §5-§11 — the Communications landing experience.
 * Deliberately not a Filament Resource table: attention, filters, and
 * the deliberate two-field create decision are curated, not generic
 * CRUD (§5, Filament_Product_Experience_Review.md).
 */
class OrganizationCommunications extends Page
{
    use InteractsWithOrganizationWorkspace, WithPagination;

    protected string $view = 'filament.organization.pages.communications';

    protected static string $routePath = '/communications';

    protected static ?string $title = 'Communications';

    protected static ?string $slug = 'communications';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static ?int $navigationSort = 2;

    public string $search = '';

    public string $kindFilter = '';

    public string $stateFilter = '';

    public function updatedSearch(): void
    {
        $this->resetPage('communicationsPage');
    }

    public function updatedKindFilter(): void
    {
        $this->resetPage('communicationsPage');
    }

    public function updatedStateFilter(): void
    {
        $this->resetPage('communicationsPage');
    }

    public function communications(): LengthAwarePaginator
    {
        return app(OrganizationCommunicationQuery::class)->paginate($this->kindFilter, $this->stateFilter, $this->search);
    }

    /** @return array{awaiting_review: int, changes_requested: int} */
    public function attention(): array
    {
        return app(OrganizationCommunicationQuery::class)->attention();
    }

    public function canCreate(): bool
    {
        return app(OrganizationScopeResolver::class)->unitsInScope(OrganizationCapability::CommunicationsCreate)->exists();
    }

    public function detailUrl(int $id): string
    {
        return OrganizationCommunicationDetail::getUrl(['record' => $id], panel: 'organization');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createCommunication')
                ->label('Create communication')
                ->icon('heroicon-o-plus')
                ->visible(fn (): bool => $this->canCreate())
                ->modalHeading('What are you preparing?')
                ->modalSubmitActionLabel('Start draft')
                ->schema([
                    Select::make('kind')
                        ->label('Type')
                        ->options([
                            OrganizationCommunicationKind::COMMUNICATION->value => 'Communication — announcements, guidance, resources',
                            OrganizationCommunicationKind::CAMPAIGN->value => 'Shared campaign — coordinated dates and reusable resources',
                        ])
                        ->default(OrganizationCommunicationKind::COMMUNICATION->value)
                        ->required()
                        ->native(false),
                    TextInput::make('title')
                        ->label('Title')
                        ->required()
                        ->maxLength(255),
                    Textarea::make('summary')
                        ->label('Purpose / summary')
                        ->rows(3)
                        ->maxLength(2000),
                    Select::make('governing_unit_id')
                        ->label('Governing scope')
                        ->helperText('This communication can later be shared only within this scope and the Units below it.')
                        ->options(fn (): array => app(OrganizationCommunicationQuery::class)->governingUnitOptions())
                        ->required()
                        ->searchable()
                        ->native(false),
                ])
                ->action(function (array $data): void {
                    $unit = OrganizationUnit::findOrFail($data['governing_unit_id']);
                    $communication = $this->runGoverned(
                        fn () => app(OrganizationCommunicationManager::class)->create(
                            $unit,
                            OrganizationCommunicationKind::from($data['kind']),
                            [
                                'title' => $data['title'],
                                'summary' => $data['summary'] ?: null,
                                'adaptation_policy' => OrganizationCommunicationAdaptationPolicy::LOCAL_ADAPTATION_ENCOURAGED,
                            ],
                        ),
                        'Draft communication created',
                    );

                    if ($communication !== null) {
                        $this->redirect(OrganizationCommunicationDetail::getUrl(['record' => $communication->id], panel: 'organization'));
                    }
                }),
        ];
    }
}
