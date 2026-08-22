<?php

namespace App\Filament\Pages;

use App\Campaigns\CampaignCommunicationContext;
use App\Enums\FaithFlowOutputStatus;
use App\Enums\FaithFlowOutputType;
use App\Enums\FaithFlowRunStatus;
use App\FaithFlow\Actions\ApproveFaithFlowOutput;
use App\FaithFlow\Actions\EditFaithFlowOutput;
use App\Jobs\FaithFlow\AnalyzeFaithFlowSourceJob;
use App\Jobs\FaithFlow\GenerateFaithFlowOutputJob;
use App\Jobs\FaithFlow\RegenerateFaithFlowOutputJob;
use App\Models\CampaignCommunication;
use App\Models\ContentItem;
use App\Models\FaithFlowOutput;
use App\Models\FaithFlowRun;
use App\Support\TenantContext;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Attributes\Url;
use LogicException;
use ValueError;

/**
 * K-FAITHFLOW-001F — the guided FaithFlow workspace. One custom Filament
 * Page (not a resource table) per §50 of the directive: SOURCE ->
 * CANONICAL ANALYSIS -> OUTPUT SELECTION -> GENERATION -> REVIEW -> EDIT/
 * REGENERATE -> APPROVE -> CONTENT STUDIO, all inside one URL/component.
 *
 * This class is UI wiring only. Every domain decision (state machine
 * guards, idempotency, human-content preservation, approval attribution,
 * Content Studio handoff, tenancy) already lives in the existing FaithFlow
 * Actions and Policies (001B-001E/R1) — this page never reimplements any
 * of it, only calls it. Analysis/generation/regeneration are dispatched
 * through the K-ASYNC-001 foundation (TenantContext::capture() + the
 * FaithFlow Job wrappers); edit/approve are fast and deterministic, so
 * they call their Actions directly and synchronously, unchanged from 001E.
 *
 * The `$currentRun` property is deliberately not named `$run` — Livewire's
 * own hydration binds mount()/route parameters onto matching public
 * property names directly, and a raw int route parameter colliding with a
 * `?FaithFlowRun`-typed property of the same name fails hydration. The
 * route/mount parameter itself stays named `run` (it's the public URL
 * vocabulary — `faithflow/{run}`), only the internal property is renamed.
 */
class FaithFlow extends Page
{
    protected string $view = 'filament.pages.faith-flow';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-magnifying-glass';

    protected static ?string $navigationLabel = 'FaithFlow';

    protected static ?string $title = 'FaithFlow';

    protected static ?string $slug = 'faithflow/{run?}';

    public ?FaithFlowRun $currentRun = null;

    #[Url(as: 'campaign_communication')]
    public ?int $campaignCommunicationId = null;

    public ?CampaignCommunication $campaignCommunication = null;

    public string $sourceText = '';

    /** @var array<int, string> */
    public array $selectedOutputTypes = [];

    public ?int $activeOutputId = null;

    public ?int $editingOutputId = null;

    public string $editingContent = '';

    /**
     * K-FAITHFLOW-001F-R2 §17 — toggled true only when every selected
     * output has settled (Approved/Failed). Drives the inline Keryon
     * Celebration partial, not a generic persistent toast.
     */
    public bool $showCelebration = false;

    public static function canAccess(): bool
    {
        return Auth::user()?->can('viewAny', FaithFlowRun::class) ?? false;
    }

    public function mount(?int $run = null): void
    {
        Gate::authorize('viewAny', FaithFlowRun::class);

        if ($run !== null) {
            $this->currentRun = FaithFlowRun::query()->with(['outputs'])->findOrFail($run);
            Gate::authorize('view', $this->currentRun);

            if (
                $this->campaignCommunicationId !== null
                && $this->campaignCommunicationId !== $this->currentRun->campaign_communication_id
            ) {
                abort(404);
            }

            $this->campaignCommunicationId = $this->currentRun->campaign_communication_id;
            $this->activeOutputId = $this->currentRun->outputs->first()?->id;
        }

        if ($this->campaignCommunicationId !== null) {
            $context = app(CampaignCommunicationContext::class);
            try {
                $this->campaignCommunication = $this->currentRun === null
                    ? $context->forFaithFlow($this->campaignCommunicationId)
                    : $context->forFaithFlowView($this->campaignCommunicationId);
            } catch (AuthorizationException|LogicException) {
                abort(403);
            }
        }
    }

    /**
     * Recent runs for this Church, for the history/reopen experience
     * (§51) — tenant-scoped automatically via BelongsToChurch, newest
     * first, a deliberately small bounded list rather than a full
     * search/analytics screen.
     */
    public function getRecentRunsProperty(): Collection
    {
        return FaithFlowRun::query()
            ->latest()
            ->limit(8)
            ->get();
    }

    public function isProcessing(): bool
    {
        if ($this->currentRun === null) {
            return false;
        }

        if ($this->currentRun->status === FaithFlowRunStatus::ANALYZING) {
            return true;
        }

        return $this->currentRun->outputs->contains(
            fn (FaithFlowOutput $output) => $output->status === FaithFlowOutputStatus::GENERATING
        );
    }

    /**
     * Re-fetches the run/outputs from the database — the target of
     * wire:poll while any work is outstanding (§18/§25/§26). Cheap: no
     * provider call, no domain action, just a fresh read of state a
     * background worker may have changed.
     */
    public function poll(): void
    {
        $this->currentRun?->refresh();
        $this->currentRun?->load('outputs');
    }

    public function startNewSource(): void
    {
        Gate::authorize('create', FaithFlowRun::class);

        $this->redirect(static::getUrl());
    }

    /**
     * §13/§14 — the source-creation step. Persists before analysis so the
     * source itself survives even if analysis later fails (§53).
     */
    public function createSource(): void
    {
        Gate::authorize('create', FaithFlowRun::class);

        if ($this->campaignCommunicationId !== null) {
            $this->campaignCommunication = app(CampaignCommunicationContext::class)
                ->forFaithFlow($this->campaignCommunicationId);
        }

        $validated = $this->validate([
            'sourceText' => ['required', 'string', 'min:100', 'max:60000'],
        ], [], [
            'sourceText' => 'source text',
        ]);

        $this->currentRun = new FaithFlowRun([
            'source_text' => $validated['sourceText'],
            'source_char_count' => mb_strlen($validated['sourceText']),
        ]);
        $this->currentRun->forceFill([
            'campaign_communication_id' => $this->campaignCommunication?->id,
        ])->save();

        $this->redirect(static::getUrl(['run' => $this->currentRun->id]));
    }

    /**
     * §15/§16/§17 — dispatches the existing, unmodified canonical-analysis
     * Action through the K-ASYNC-001 foundation. Authorization happens
     * before context capture, exactly as K-ASYNC-001's own documentation
     * requires.
     */
    public function analyze(): void
    {
        if ($this->currentRun === null) {
            return;
        }

        Gate::authorize('analyze', $this->currentRun);

        if (! in_array($this->currentRun->status, [FaithFlowRunStatus::DRAFT, FaithFlowRunStatus::ANALYSIS_FAILED], true)) {
            throw new LogicException("A run with status [{$this->currentRun->status->value}] cannot be analyzed.");
        }

        $context = app(TenantContext::class)->capture();

        AnalyzeFaithFlowSourceJob::dispatch($context, $this->currentRun->id);

        Notification::make()
            ->title('Reading your source…')
            ->body('FaithFlow is understanding the message. This page will update automatically.')
            ->info()
            ->send();
    }

    /**
     * §16/§24 — one independent job per selected output type, mirroring
     * GenerateSelectedFaithFlowOutputs' own row-creation contract exactly
     * (same idempotent find-or-create, same race-safety) but dispatching
     * instead of generating synchronously, so an HTTP request never blocks
     * on up to 8 provider calls.
     */
    public function generateSelected(): void
    {
        if ($this->currentRun === null || $this->currentRun->status !== FaithFlowRunStatus::ANALYZED) {
            return;
        }

        if ($this->selectedOutputTypes === []) {
            Notification::make()->title('Choose at least one output to create.')->warning()->send();

            return;
        }

        $context = app(TenantContext::class)->capture();

        foreach ($this->selectedOutputTypes as $value) {
            try {
                $type = FaithFlowOutputType::from($value);
            } catch (ValueError) {
                continue;
            }

            $output = $this->findOrCreateOutput($this->currentRun, $type);

            Gate::authorize('generate', $output);

            if (in_array($output->status, [FaithFlowOutputStatus::PENDING, FaithFlowOutputStatus::FAILED], true)) {
                GenerateFaithFlowOutputJob::dispatch($context, $output->id);
            }
        }

        $this->currentRun->load('outputs');
        $this->activeOutputId ??= $this->currentRun->outputs->first()?->id;
    }

    public function retryOutput(int $outputId): void
    {
        $output = FaithFlowOutput::query()->findOrFail($outputId);

        Gate::authorize('generate', $output);

        $context = app(TenantContext::class)->capture();

        GenerateFaithFlowOutputJob::dispatch($context, $output->id);
    }

    /**
     * §16/§33 — the human-facing consequence is explained in the view, not
     * here; this method only dispatches. Domain behavior (edited content
     * preserved, unedited content replaced) is unchanged 001D behavior.
     */
    public function regenerateOutput(int $outputId): void
    {
        $output = FaithFlowOutput::query()->findOrFail($outputId);

        Gate::authorize('regenerate', $output);

        $context = app(TenantContext::class)->capture();

        RegenerateFaithFlowOutputJob::dispatch($context, $output->id);
    }

    public function selectOutput(int $outputId): void
    {
        $this->activeOutputId = $outputId;
        $this->editingOutputId = null;
    }

    public function startEditing(int $outputId): void
    {
        $output = FaithFlowOutput::query()->findOrFail($outputId);

        Gate::authorize('edit', $output);

        // Defense in depth — the edit control is not rendered for a
        // non-GENERATED output, so reaching this guard means the normal
        // UI path was bypassed (e.g. a stale/tampered wire:click).
        if (! $output->isEditable()) {
            throw new LogicException("An output with status [{$output->status->value}] cannot be edited.");
        }

        $this->editingOutputId = $outputId;
        $this->editingContent = (string) $output->content;
    }

    public function cancelEditing(): void
    {
        $this->editingOutputId = null;
        $this->editingContent = '';
    }

    /**
     * §30 — calls the existing deterministic EditFaithFlowOutput Action
     * unchanged. The UI only ever touches `content`; `generated_content`/
     * provenance are never exposed as editable fields.
     */
    public function saveEdit(int $outputId): void
    {
        $output = FaithFlowOutput::query()->findOrFail($outputId);

        Gate::authorize('edit', $output);

        try {
            app(EditFaithFlowOutput::class)->handle($output, $this->editingContent);
        } catch (InvalidArgumentException) {
            Notification::make()->title('This output cannot be saved empty.')->danger()->send();

            return;
        } catch (LogicException) {
            Notification::make()->title('This output can no longer be edited.')->danger()->send();

            return;
        }

        $this->editingOutputId = null;
        $this->currentRun?->load('outputs');

        Notification::make()->title('Your changes are saved.')->success()->send();
    }

    /**
     * §34/§35/§36 — approval + conditional Content Studio handoff, via the
     * existing atomic ApproveFaithFlowOutput Action. The caller checks
     * both FaithFlow (`approve`) and, for mapped types, Content Studio
     * (`create` on ContentItem) authorization before invoking it — see
     * ApproveFaithFlowOutput's own docblock for why this class does not,
     * and must not, assume one implies the other.
     */
    public function approveOutput(int $outputId): void
    {
        $output = FaithFlowOutput::query()->findOrFail($outputId);

        Gate::authorize('approve', $output);

        if ($output->output_type->contentType() !== null && Gate::denies('create', ContentItem::class)) {
            Notification::make()
                ->title('You can approve this, but Content Studio access is required to send it there.')
                ->danger()
                ->send();

            return;
        }

        try {
            $result = app(ApproveFaithFlowOutput::class)->handle($output);
        } catch (LogicException) {
            Notification::make()->title('This output cannot be approved right now.')->danger()->send();

            return;
        }

        $this->currentRun?->load('outputs');

        if ($result->content_item_id !== null) {
            Notification::make()
                ->title('Added to Content Studio')
                ->body($result->output_type->label().' is now a draft in Content Studio, ready for its own review.')
                ->success()
                ->send();
        } else {
            Notification::make()->title('Marked as reviewed.')->success()->send();
        }

        // K-FAITHFLOW-001F-R2 §17 — a meaningful milestone (every selected
        // output has left the "still working on it" states) gets the real
        // Keryon Celebration treatment (rendered inline in the view, see
        // `ff-celebration`), not fired per individual approval and not a
        // generic persistent toast.
        if ($this->currentRun !== null && $this->allSelectedOutputsAreSettled()) {
            $this->showCelebration = true;
        }
    }

    public function dismissCelebration(): void
    {
        $this->showCelebration = false;
    }

    public function campaignWorkspaceUrl(): ?string
    {
        return $this->campaignCommunication === null
            ? null
            : CampaignWorkspace::getUrl(['campaign' => $this->campaignCommunication->campaign_id]);
    }

    protected function allSelectedOutputsAreSettled(): bool
    {
        if ($this->currentRun === null || $this->currentRun->outputs->isEmpty()) {
            return false;
        }

        return $this->currentRun->outputs->every(
            fn (FaithFlowOutput $output) => in_array($output->status, [
                FaithFlowOutputStatus::APPROVED,
                FaithFlowOutputStatus::FAILED,
            ], true)
        );
    }

    /**
     * Mirrors GenerateSelectedFaithFlowOutputs::findOrCreateOutput()
     * exactly (same idempotent, race-safe construction) — duplicated
     * rather than reused because that method is private to a synchronous
     * orchestrator this page deliberately does not call (§16 — dispatch
     * independent jobs instead of one synchronous batch).
     */
    protected function findOrCreateOutput(FaithFlowRun $run, FaithFlowOutputType $type): FaithFlowOutput
    {
        $existing = FaithFlowOutput::query()
            ->where('faithflow_run_id', $run->id)
            ->where('output_type', $type->value)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $output = new FaithFlowOutput(['output_type' => $type]);
        $output->church_id = $run->church_id;
        $output->faithflow_run_id = $run->id;

        try {
            $output->save();
        } catch (QueryException) {
            return FaithFlowOutput::query()
                ->where('faithflow_run_id', $run->id)
                ->where('output_type', $type->value)
                ->firstOrFail();
        }

        // The DB-level default (status = 'pending') is not reflected on
        // this in-memory instance until refreshed — without this, the
        // dispatch guard below reads a null status and silently skips
        // dispatch for a genuinely brand-new row.
        return $output->refresh();
    }
}
