<?php

namespace App\Filament\Organization\Pages;

use App\Enums\OrganizationCommunicationAdaptationPolicy;
use App\Enums\OrganizationCommunicationAssetRightsBasis;
use App\Enums\OrganizationCommunicationDeclineReasonCode;
use App\Enums\OrganizationCommunicationDistributionState;
use App\Enums\OrganizationCommunicationKind;
use App\Enums\OrganizationCommunicationMaterialType;
use App\Enums\OrganizationCommunicationRevisionState;
use App\Enums\OrganizationCommunicationTargetMode;
use App\Filament\Organization\Concerns\InteractsWithOrganizationWorkspace;
use App\Models\Church;
use App\Models\OrganizationCommunication;
use App\Models\OrganizationCommunicationAsset;
use App\Models\OrganizationCommunicationDistribution;
use App\Models\OrganizationCommunicationMaterial;
use App\Models\OrganizationCommunicationRevision;
use App\Models\OrganizationUnit;
use App\Organizations\Communications\Distribution\OrganizationCommunicationAudienceResolver;
use App\Organizations\Communications\Distribution\OrganizationCommunicationDistributionManager;
use App\Organizations\Communications\OrganizationCommunicationAssetManager;
use App\Organizations\Communications\OrganizationCommunicationManager;
use App\Organizations\Communications\OrganizationCommunicationWorkflow;
use App\Organizations\Communications\Read\OrganizationCommunicationQuery;
use App\Organizations\Communications\Tracking\Dto\OrganizationCommunicationDistributionTracking;
use App\Organizations\Communications\Tracking\Dto\OrganizationCommunicationTrackingSummary;
use App\Organizations\Communications\Tracking\OrganizationCommunicationDeliveryOutcomeResolver;
use App\Organizations\Communications\Tracking\OrganizationCommunicationTrackingQuery;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\WithPagination;
use Throwable;

/**
 * K-ORG-COMMS-001B §12-§13/§25-§33 — the dedicated authoring/review
 * workspace. Sections (Overview, Resources, Guidance, Review, History)
 * separate concerns instead of one giant database form (§12). Every
 * mutation goes through OrganizationCommunicationManager/Workflow/
 * AssetManager — never a raw Eloquent update — so server-managed
 * ownership, lifecycle, and approval attribution can never be
 * mass-assigned from this page (§14).
 */
class OrganizationCommunicationDetail extends Page
{
    use InteractsWithOrganizationWorkspace;
    use WithPagination;

    protected string $view = 'filament.organization.pages.communication-detail';

    protected static ?string $slug = 'communications/{record}';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Communication';

    public int $record;

    private ?OrganizationCommunication $communicationCache = null;

    public function mount(): void
    {
        // Fails closed (404/403) for cross-scope or cross-Organization
        // ids via OrganizationCommunicationQuery's own scoped lookup.
        $this->communication();
    }

    /**
     * Memoized per-request: several accessors below (revision(),
     * governingUnitPath(), auditEvents(), canSubmit(), the Action
     * closures) each independently need the communication, and the
     * underlying query is not cheap enough to repeat several times per
     * render.
     */
    public function communication(): OrganizationCommunication
    {
        return $this->communicationCache ??= app(OrganizationCommunicationQuery::class)->find($this->record);
    }

    public function revision(): OrganizationCommunicationRevision
    {
        return $this->communication()->revisions->last();
    }

    public function governingUnitPath(): string
    {
        $communication = $this->communication();

        return app(OrganizationCommunicationQuery::class)->unitPath($communication->governing_unit_id, $communication->governingUnit?->name);
    }

    public function stateLabel(string $state): string
    {
        return app(OrganizationCommunicationQuery::class)->stateLabel($state);
    }

    public function backUrl(): string
    {
        return OrganizationCommunications::getUrl(panel: 'organization');
    }

    /** @return Collection<int, array{label: string, occurred_at: string, actor: ?string}> */
    public function auditEvents(): Collection
    {
        return app(OrganizationCommunicationQuery::class)->auditEvents($this->communication());
    }

    /**
     * `runGoverned()` reports success/failure; this additionally drops
     * the memoized communication so the very next render (which happens
     * within the same Livewire request, right after this action runs)
     * reflects the mutation instead of the pre-action snapshot.
     */
    private function runGovernedFresh(callable $operation, string $success): mixed
    {
        $result = $this->runGoverned($operation, $success);
        $this->communicationCache = null;

        return $result;
    }

    public function canSubmit(): bool
    {
        $revision = $this->revision();

        return $revision->state === OrganizationCommunicationRevisionState::DRAFT
            && $revision->materials->isNotEmpty()
            && (bool) auth()->user()?->can('submit', $this->communication());
    }

    // ---------------------------------------------------------------
    // Overview / Guidance drafting — modal-driven, like every other
    // mutation on this page, per Keryon's Product-first Filament
    // convention (no persistent giant database form, §12).
    // ---------------------------------------------------------------

    public function editOverviewAction(): Action
    {
        return Action::make('editOverview')
            ->label('Edit overview')
            ->modalHeading('Edit overview')
            ->fillForm(fn (): array => [
                'title' => $this->revision()->title,
                'summary' => $this->revision()->summary,
                'requested_action' => $this->revision()->requested_action,
            ])
            ->schema([
                TextInput::make('title')->label('Title')->required()->maxLength(255),
                Textarea::make('summary')->label('Purpose / summary')->rows(3)->maxLength(2000),
                Textarea::make('requested_action')
                    ->label('What should Churches do with this?')
                    ->helperText('Guidance only — this does not trigger any automatic Church action.')
                    ->rows(3)
                    ->maxLength(2000),
            ])
            ->action(function (array $data): void {
                $this->runGovernedFresh(
                    fn () => app(OrganizationCommunicationManager::class)->updateDraftRevision($this->revision(), [
                        'title' => $data['title'],
                        'summary' => $data['summary'] ?: null,
                        'requested_action' => $data['requested_action'] ?: null,
                    ]),
                    'Draft saved',
                );
            });
    }

    public function editGuidanceAction(): Action
    {
        $revision = $this->revision();
        $isCampaign = $this->communication()->kind === OrganizationCommunicationKind::CAMPAIGN;

        return Action::make('editGuidance')
            ->label('Edit guidance')
            ->modalHeading('Edit guidance')
            ->fillForm(fn (): array => [
                'adaptation_policy' => $revision->adaptation_policy->value,
                'campaign_starts_on' => $revision->campaign_starts_on?->toDateString(),
                'campaign_ends_on' => $revision->campaign_ends_on?->toDateString(),
                'recommended_response_on' => $revision->recommended_response_on?->toDateString(),
                'suggested_publish_by' => $revision->suggested_publish_by?->toDateString(),
                'available_from' => $revision->available_from?->toDateTimeString(),
                'available_until' => $revision->available_until?->toDateTimeString(),
            ])
            ->schema([
                Select::make('adaptation_policy')
                    ->label('Local adaptation guidance')
                    ->options($this->adaptationPolicyOptions())
                    ->helperText(fn (callable $get): string => OrganizationCommunicationAdaptationPolicy::from($get('adaptation_policy'))->helperText())
                    ->live()
                    ->required()
                    ->native(false),
                Section::make('Campaign dates')
                    ->visible($isCampaign)
                    ->schema([
                        DatePicker::make('campaign_starts_on')->label('Campaign start'),
                        DatePicker::make('campaign_ends_on')->label('Campaign end'),
                    ]),
                DatePicker::make('recommended_response_on')->label('Recommended response date'),
                DatePicker::make('suggested_publish_by')->label('Suggested publish-by date'),
                DateTimePicker::make('available_from')->label('Available from'),
                DateTimePicker::make('available_until')->label('Available until'),
            ])
            ->action(function (array $data): void {
                $this->runGovernedFresh(
                    fn () => app(OrganizationCommunicationManager::class)->updateDraftRevision($this->revision(), [
                        'adaptation_policy' => $data['adaptation_policy'],
                        'campaign_starts_on' => $data['campaign_starts_on'] ?: null,
                        'campaign_ends_on' => $data['campaign_ends_on'] ?: null,
                        'recommended_response_on' => $data['recommended_response_on'] ?: null,
                        'suggested_publish_by' => $data['suggested_publish_by'] ?: null,
                        'available_from' => $data['available_from'] ?: null,
                        'available_until' => $data['available_until'] ?: null,
                    ]),
                    'Guidance saved',
                );
            });
    }

    // ---------------------------------------------------------------
    // Materials (communication resources)
    // ---------------------------------------------------------------

    public function addMaterialAction(): Action
    {
        return Action::make('addMaterial')
            ->label('Add resource')
            ->icon('heroicon-o-plus')
            ->modalHeading('Add a communication resource')
            ->schema([
                Select::make('type')
                    ->label('Resource type')
                    ->options(collect(OrganizationCommunicationMaterialType::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                    ->required()
                    ->native(false),
                TextInput::make('title')->label('Label (optional)')->maxLength(255),
                MarkdownEditor::make('body')->label('Content')->required(),
            ])
            ->action(function (array $data): void {
                $this->runGovernedFresh(
                    fn () => app(OrganizationCommunicationManager::class)->addMaterial(
                        $this->revision(),
                        OrganizationCommunicationMaterialType::from($data['type']),
                        $data['body'],
                        $data['title'] ?: null,
                    ),
                    'Resource added',
                );
            });
    }

    public function editMaterialAction(): Action
    {
        return Action::make('editMaterial')
            ->label('Edit')
            ->modalHeading('Edit resource')
            ->fillForm(function (array $arguments): array {
                $material = OrganizationCommunicationMaterial::findOrFail($arguments['material']);

                return ['type' => $material->type->value, 'title' => $material->title, 'body' => $material->body];
            })
            ->schema([
                Select::make('type')
                    ->label('Resource type')
                    ->options(collect(OrganizationCommunicationMaterialType::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                    ->required()
                    ->native(false),
                TextInput::make('title')->label('Label (optional)')->maxLength(255),
                MarkdownEditor::make('body')->label('Content')->required(),
            ])
            ->action(function (array $data, array $arguments): void {
                $material = OrganizationCommunicationMaterial::findOrFail($arguments['material']);
                $this->runGovernedFresh(
                    fn () => app(OrganizationCommunicationManager::class)->updateMaterial($material, [
                        'type' => OrganizationCommunicationMaterialType::from($data['type']),
                        'title' => $data['title'] ?: null,
                        'body' => $data['body'],
                    ]),
                    'Resource updated',
                );
            });
    }

    public function deleteMaterialAction(): Action
    {
        return Action::make('deleteMaterial')
            ->label('Delete')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Remove this resource?')
            ->action(function (array $arguments): void {
                $material = OrganizationCommunicationMaterial::findOrFail($arguments['material']);
                $this->runGovernedFresh(
                    fn () => app(OrganizationCommunicationManager::class)->removeMaterial($material),
                    'Resource removed',
                );
            });
    }

    public function moveMaterial(int $materialId, string $direction): void
    {
        $material = OrganizationCommunicationMaterial::findOrFail($materialId);
        $this->runGovernedFresh(
            fn () => app(OrganizationCommunicationManager::class)->moveMaterial($material, $direction),
            'Resource order updated',
        );
    }

    // ---------------------------------------------------------------
    // Assets (Organization-owned files)
    // ---------------------------------------------------------------

    public function assetStagingDirectory(): string
    {
        return app(OrganizationCommunicationAssetManager::class)->stagingDirectory();
    }

    public function addAssetAction(): Action
    {
        return Action::make('addAsset')
            ->label('Add official asset')
            ->icon('heroicon-o-paper-clip')
            ->modalHeading('Add an Organization asset')
            ->schema([
                FileUpload::make('upload')
                    ->label('File')
                    ->acceptedFileTypes(OrganizationCommunicationAssetManager::ACCEPTED_MIME_TYPES)
                    ->maxSize(OrganizationCommunicationAssetManager::MAX_UPLOAD_SIZE_KB)
                    ->disk(fn () => config('media.private_disk', 'media-private'))
                    ->directory(fn () => app(OrganizationCommunicationAssetManager::class)->stagingDirectory())
                    ->storeFileNamesIn('original_filename')
                    ->required()
                    ->helperText('JPEG, PNG, WebP, or PDF — up to 15 MB.'),
                TextInput::make('alt_text')->label('Alt text (for images)')->maxLength(255),
                Select::make('rights_basis')
                    ->label('Rights basis')
                    ->options(collect(OrganizationCommunicationAssetRightsBasis::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                    ->required()
                    ->native(false),
                Textarea::make('usage_guidance')
                    ->label('Usage guidance (optional)')
                    ->rows(2)
                    ->helperText('Example: Churches may add local service details. Do not remove the campaign identity.'),
                Toggle::make('attribution_required')->label('Attribution required?')->live(),
                TextInput::make('attribution_text')
                    ->label('Attribution text')
                    ->maxLength(255)
                    ->requiredIf('attribution_required', true)
                    ->visible(fn (callable $get) => (bool) $get('attribution_required')),
            ])
            ->action(function (array $data): void {
                $this->runGovernedFresh(
                    fn () => app(OrganizationCommunicationAssetManager::class)->addAsset(
                        $this->revision(),
                        $data['upload'],
                        $data['original_filename'] ?? 'asset',
                        [
                            'rights_basis' => $data['rights_basis'],
                            'usage_guidance' => $data['usage_guidance'] ?? null,
                            'attribution_required' => (bool) ($data['attribution_required'] ?? false),
                            'attribution_text' => $data['attribution_text'] ?? null,
                            'alt_text' => $data['alt_text'] ?? null,
                        ],
                    ),
                    'Asset added',
                );
            });
    }

    public function deleteAssetAction(): Action
    {
        return Action::make('deleteAsset')
            ->label('Delete')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Remove this asset?')
            ->action(function (array $arguments): void {
                $asset = OrganizationCommunicationAsset::findOrFail($arguments['asset']);
                $this->runGovernedFresh(
                    fn () => app(OrganizationCommunicationAssetManager::class)->removeAsset($asset),
                    'Asset removed',
                );
            });
    }

    public function assetUrl(OrganizationCommunicationAsset $asset): string
    {
        return route('organization-communications.assets.private', ['asset' => $asset->uuid]);
    }

    // ---------------------------------------------------------------
    // Review workflow
    // ---------------------------------------------------------------

    public function submitForReviewAction(): Action
    {
        return Action::make('submitForReview')
            ->label('Submit for review')
            ->visible(fn (): bool => $this->canSubmit())
            ->requiresConfirmation()
            ->modalHeading('Submit this Draft for review?')
            ->modalDescription('An authorized Organization Administrator will be able to approve it or request changes.')
            ->action(function (): void {
                $this->runGovernedFresh(
                    fn () => app(OrganizationCommunicationWorkflow::class)->submit($this->revision()),
                    'Submitted for review',
                );
            });
    }

    public function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Approve this revision?')
            ->modalDescription('Approved revisions become read-only. Distribution is a separate, later step.')
            ->action(function (): void {
                $this->runGovernedFresh(
                    fn () => app(OrganizationCommunicationWorkflow::class)->approve($this->revision()),
                    'Revision approved',
                );
            });
    }

    public function requestChangesAction(): Action
    {
        return Action::make('requestChanges')
            ->label('Request changes')
            ->color('warning')
            ->modalHeading('Request changes')
            ->schema([
                Textarea::make('feedback')->label('What needs to change?')->required()->rows(4),
            ])
            ->action(function (array $data): void {
                $this->runGovernedFresh(
                    fn () => app(OrganizationCommunicationWorkflow::class)->requestChanges($this->revision(), $data['feedback']),
                    'Changes requested',
                );
            });
    }

    public function resumeEditingAction(): Action
    {
        return Action::make('resumeEditing')
            ->label('Resume editing')
            ->requiresConfirmation()
            ->modalHeading('Return this revision to Draft?')
            ->action(function (): void {
                $this->runGovernedFresh(
                    fn () => app(OrganizationCommunicationWorkflow::class)->returnToDraft($this->revision()),
                    'Returned to Draft',
                );
            });
    }

    public function createNextRevisionAction(): Action
    {
        return Action::make('createNextRevision')
            ->label('Create new revision')
            ->requiresConfirmation()
            ->modalHeading('Start a new Draft revision?')
            ->modalDescription('This copies the current approved content and resources into a new editable Draft. The approved revision is unaffected.')
            ->action(function (): void {
                $this->runGovernedFresh(
                    fn () => app(OrganizationCommunicationManager::class)->createNextRevision($this->revision()),
                    'New Draft revision created',
                );
            });
    }

    public function closeAction(): Action
    {
        return Action::make('close')
            ->label('Close communication')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Close this communication?')
            ->modalDescription('Closing ends this communication. It cannot be reopened.')
            ->action(function (): void {
                $this->runGovernedFresh(
                    fn () => app(OrganizationCommunicationWorkflow::class)->close($this->communication()),
                    'Communication closed',
                );
            });
    }

    public function deleteDraftAction(): Action
    {
        return Action::make('deleteDraft')
            ->label('Delete draft')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete this Draft communication?')
            ->modalDescription('This never-distributed Draft will be removed.')
            ->action(function (): void {
                $communication = $this->communication();
                $result = $this->runGovernedFresh(
                    fn () => app(OrganizationCommunicationManager::class)->deleteDraft($communication) ?? true,
                    'Draft deleted',
                );

                if ($result !== null) {
                    $this->redirect($this->backUrl());
                }
            });
    }

    // ---------------------------------------------------------------
    // Distribution — K-ORG-COMMS-001C §32-§34
    // ---------------------------------------------------------------

    public function canDistribute(): bool
    {
        $revision = $this->revision();

        return in_array($revision->state, [
            OrganizationCommunicationRevisionState::APPROVED,
            OrganizationCommunicationRevisionState::DISTRIBUTED,
        ], true) && (bool) auth()->user()?->can('distribute', $this->communication());
    }

    public function distributeAction(): Action
    {
        return Action::make('distribute')
            ->label('Distribute')
            ->color('primary')
            ->visible(fn (): bool => $this->canDistribute())
            ->steps([
                Wizard\Step::make('Audience')
                    ->schema([
                        Select::make('target_mode')
                            ->label('Who should receive this?')
                            ->options([
                                OrganizationCommunicationTargetMode::GOVERNING_SCOPE->value => 'Entire governing scope — '.$this->governingUnitPath(),
                                OrganizationCommunicationTargetMode::UNIT_SUBTREE->value => 'A specific Unit within the governing scope',
                                OrganizationCommunicationTargetMode::EXPLICIT_CHURCHES->value => 'Specific Churches',
                            ])
                            ->default(OrganizationCommunicationTargetMode::GOVERNING_SCOPE->value)
                            ->live()
                            ->required()
                            ->native(false),
                        Select::make('target_unit_id')
                            ->label('Unit')
                            ->helperText('Only Units within this communication\'s governing scope are offered.')
                            ->options(fn (): array => $this->subtreeUnitOptions())
                            ->searchable()
                            ->native(false)
                            ->live()
                            ->visible(fn (callable $get): bool => $get('target_mode') === OrganizationCommunicationTargetMode::UNIT_SUBTREE->value)
                            ->required(fn (callable $get): bool => $get('target_mode') === OrganizationCommunicationTargetMode::UNIT_SUBTREE->value),
                        Select::make('church_ids')
                            ->label('Churches')
                            ->multiple()
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => $this->searchEligibleChurches($search))
                            ->getOptionLabelsUsing(fn (array $values): array => Church::query()->whereIn('id', $values)->pluck('name', 'id')->all())
                            ->live()
                            ->visible(fn (callable $get): bool => $get('target_mode') === OrganizationCommunicationTargetMode::EXPLICIT_CHURCHES->value)
                            ->required(fn (callable $get): bool => $get('target_mode') === OrganizationCommunicationTargetMode::EXPLICIT_CHURCHES->value),
                    ]),
                Wizard\Step::make('Preview')
                    ->schema([
                        Placeholder::make('preview')
                            ->label('Resolved recipients')
                            ->content(fn (callable $get): string => $this->audiencePreviewSummary($get('target_mode'), $get('target_unit_id'), $get('church_ids'))),
                    ]),
                Wizard\Step::make('Confirm')
                    ->schema([
                        Placeholder::make('confirm')
                            ->label('Ready to distribute')
                            ->content(fn (callable $get): string => $this->distributionConfirmationCopy($get('target_mode'), $get('target_unit_id'), $get('church_ids'))),
                    ]),
            ])
            ->modalHeading('Distribute this approved communication')
            ->modalSubmitActionLabel('Distribute')
            ->action(function (array $data): void {
                $mode = OrganizationCommunicationTargetMode::from($data['target_mode']);
                $this->runGovernedFresh(
                    fn () => app(OrganizationCommunicationDistributionManager::class)->request(
                        $this->revision(),
                        $mode,
                        $data['target_unit_id'] ?? null,
                        $data['church_ids'] ?? null,
                    ),
                    'Distribution started',
                );
            });
    }

    /** @return array<int, string> */
    private function subtreeUnitOptions(): array
    {
        $communication = $this->communication();

        return OrganizationUnit::query()
            ->whereIn('id', DB::table('organization_unit_paths')
                ->where('organization_id', $communication->organization_id)
                ->where('ancestor_id', $communication->governing_unit_id)
                ->select('descendant_id'))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /** @return array<int, string> */
    private function searchEligibleChurches(string $search): array
    {
        $communication = $this->communication();

        return app(OrganizationCommunicationAudienceResolver::class)
            ->preview($communication->organization_id, $communication->governing_unit_id, null, $search, 25)
            ->getCollection()
            ->pluck('name', 'id')
            ->all();
    }

    /** @param list<int>|null $churchIds */
    private function resolvedAudienceCount(?string $mode, ?int $unitId, ?array $churchIds): ?int
    {
        if ($mode === null) {
            return null;
        }

        try {
            $resolver = app(OrganizationCommunicationAudienceResolver::class);
            $target = $resolver->validateTarget($this->communication(), OrganizationCommunicationTargetMode::from($mode), $unitId, $churchIds);

            return $resolver->previewCount($this->communication()->organization_id, $target['unitId'], $target['churchIds']);
        } catch (Throwable) {
            return null;
        }
    }

    /** @param list<int>|null $churchIds */
    public function audiencePreviewSummary(?string $mode, ?int $unitId, ?array $churchIds): string
    {
        $count = $this->resolvedAudienceCount($mode, $unitId, $churchIds);

        return match (true) {
            $count === null => 'Choose a valid audience to see resolved recipients.',
            $count === 0 => 'No eligible Churches match this audience yet.',
            $count === 1 => '1 Church currently matches this audience.',
            default => "{$count} Churches currently match this audience.",
        };
    }

    /** @param list<int>|null $churchIds */
    public function distributionConfirmationCopy(?string $mode, ?int $unitId, ?array $churchIds): string
    {
        $count = $this->resolvedAudienceCount($mode, $unitId, $churchIds);
        $headline = match (true) {
            $count === null => 'Make this approved communication available?',
            $count === 0 => 'No eligible Churches currently match this audience.',
            $count === 1 => 'Make this approved communication available to 1 Church?',
            default => "Make this approved communication available to {$count} Churches?",
        };

        return $headline."\n\nChurches will receive the approved Organization version. This does not publish anything on their behalf — the count above reflects current eligibility and may be re-confirmed at the moment of distribution.";
    }

    /** @return Collection<int, OrganizationCommunicationDistribution> */
    public function distributions(): Collection
    {
        return $this->communication()->distributions()
            ->with(['revision', 'targetUnit', 'initiatorMembership.user'])
            ->limit(10)
            ->get();
    }

    public function distributionStateLabel(OrganizationCommunicationDistributionState $state): string
    {
        return $state->label();
    }

    public function distributionTargetSummary(OrganizationCommunicationDistribution $distribution): string
    {
        return match ($distribution->target_mode) {
            OrganizationCommunicationTargetMode::GOVERNING_SCOPE => 'Entire governing scope',
            OrganizationCommunicationTargetMode::UNIT_SUBTREE => 'Unit: '.($distribution->targetUnit?->name ?? '—'),
            OrganizationCommunicationTargetMode::EXPLICIT_CHURCHES => count($distribution->target_church_ids ?? []).' selected Churches',
        };
    }

    // ---------------------------------------------------------------
    // K-ORG-COMMS-001F — Tracking (§18-§27). Read-only: factual
    // delivery-outcome visibility only, never Church-local detail.
    // ---------------------------------------------------------------

    public ?int $trackingDistributionId = null;

    public string $trackingOutcomeFilter = '';

    public string $trackingSearch = '';

    public function canViewTracking(): bool
    {
        return (bool) auth()->user()?->can('viewDistributions', $this->communication());
    }

    /** @return array<int, OrganizationCommunicationTrackingSummary> */
    public function trackingSummary(): array
    {
        return app(OrganizationCommunicationTrackingQuery::class)->communicationSummary($this->communication());
    }

    /** @return Collection<int, OrganizationCommunicationDistribution> */
    public function trackingDistributionsForRevision(int $revisionId): Collection
    {
        return $this->distributions()->where('organization_communication_revision_id', $revisionId)->values();
    }

    public function outcomeLabel(string $outcome): string
    {
        return app(OrganizationCommunicationDeliveryOutcomeResolver::class)->label($outcome);
    }

    public function declineReasonLabel(string $code): string
    {
        return OrganizationCommunicationDeclineReasonCode::tryFrom($code)?->label() ?? $code;
    }

    public function selectTrackingDistribution(int $distributionId): void
    {
        $this->trackingDistributionId = $distributionId;
        $this->trackingOutcomeFilter = '';
        $this->trackingSearch = '';
        $this->resetPage('recipientsPage');
    }

    public function updatedTrackingOutcomeFilter(): void
    {
        $this->resetPage('recipientsPage');
    }

    public function updatedTrackingSearch(): void
    {
        $this->resetPage('recipientsPage');
    }

    public function selectedDistributionTracking(): ?OrganizationCommunicationDistributionTracking
    {
        $distribution = $this->selectedDistribution();

        return $distribution === null
            ? null
            : app(OrganizationCommunicationTrackingQuery::class)->distributionTracking($distribution);
    }

    public function trackingRecipients(): ?LengthAwarePaginator
    {
        $distribution = $this->selectedDistribution();

        if ($distribution === null) {
            return null;
        }

        return app(OrganizationCommunicationTrackingQuery::class)->recipients(
            $distribution,
            $this->trackingOutcomeFilter,
            $this->trackingSearch,
        );
    }

    private function selectedDistribution(): ?OrganizationCommunicationDistribution
    {
        if ($this->trackingDistributionId === null) {
            return null;
        }

        return OrganizationCommunicationDistribution::query()
            ->where('organization_communication_id', $this->communication()->id)
            ->with(['revision', 'communication'])
            ->find($this->trackingDistributionId);
    }

    /** @return array<string, string> */
    public function kindOptions(): array
    {
        return [
            OrganizationCommunicationKind::COMMUNICATION->value => 'Communication',
            OrganizationCommunicationKind::CAMPAIGN->value => 'Shared campaign',
        ];
    }

    /** @return array<string, string> */
    public function adaptationPolicyOptions(): array
    {
        return collect(OrganizationCommunicationAdaptationPolicy::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all();
    }
}
