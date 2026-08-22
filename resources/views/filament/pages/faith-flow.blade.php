@php
    use App\Enums\FaithFlowOutputType;
    use App\Enums\FaithFlowOutputStatus;
    use App\Enums\FaithFlowRunStatus;

    $outputDescriptions = [
        FaithFlowOutputType::SERMON_SUMMARY->value => 'A concise overview of the message, ready to share.',
        FaithFlowOutputType::KEY_THEMES->value => 'The main themes running through the message, for your own reference.',
        FaithFlowOutputType::KEY_QUOTES->value => 'Notable statements worth highlighting, drawn directly from your source.',
        FaithFlowOutputType::DEVOTIONAL->value => 'A short reflection based on the teaching, for daily use.',
        FaithFlowOutputType::PRAYER_POINTS->value => 'Prayer prompts grounded in the message.',
        FaithFlowOutputType::SOCIAL_CAPTIONS->value => 'Shareable caption options for social media.',
        FaithFlowOutputType::WHATSAPP_STATUS_COPY->value => 'Concise copy for WhatsApp or status updates.',
        FaithFlowOutputType::DISCUSSION_QUESTIONS->value => 'Questions to guide a small group or class discussion.',
    ];

    $runStatusColor = fn (FaithFlowRunStatus $status) => match ($status) {
        FaithFlowRunStatus::DRAFT => 'gray',
        FaithFlowRunStatus::ANALYZING => 'warning',
        FaithFlowRunStatus::ANALYZED => 'success',
        FaithFlowRunStatus::ANALYSIS_FAILED => 'danger',
    };

    $outputStatusColor = fn (FaithFlowOutputStatus $status) => match ($status) {
        FaithFlowOutputStatus::PENDING => 'gray',
        FaithFlowOutputStatus::GENERATING => 'warning',
        FaithFlowOutputStatus::GENERATED => 'success',
        FaithFlowOutputStatus::FAILED => 'danger',
        FaithFlowOutputStatus::APPROVED => 'success',
    };
@endphp

<x-filament-panels::page>
    <div class="ff" x-data="{}">
        @if ($currentRun === null)
            {{-- ================= FIRST USE / SOURCE ENTRY — Level A, expressive (§5/§12/§13) ================= --}}
            <div class="ff-atmosphere ff-workspace-surface relative overflow-hidden p-6 sm:p-10">
                @include('filament.components.faithflow.forms', ['variant' => 'full'])

                <div class="relative">
                    {{--
                        K-FAITHFLOW-001F-R2B — the single approved-artwork brand
                        anchor for FaithFlow (see R2B report §4/§5). Hero surface
                        is light, so the full-color (teal-background) master SVG
                        is used per the light/dark variant rule. Decorative: the
                        Filament page header already announces "FaithFlow" and
                        the persistent nav already announces "Keryon" — a second
                        accessible name here would be redundant (§13).
                    --}}
                    <img
                        src="{{ asset('branding/logo/keryon-logo.svg') }}"
                        alt=""
                        class="ff-logo"
                    />

                    <p class="ff-eyebrow">Ministry source → communications</p>
                    <h2 class="ff-hero-title mt-2">
                        What ministry material are you working with?
                    </h2>
                    <p class="ff-lede mt-3">
                        Bring in a sermon, teaching notes, or a ministry message. FaithFlow helps you understand it
                        once, then create the communications your church needs from it — you stay in control of
                        everything before it goes anywhere.
                    </p>

                    <form wire:submit="createSource" class="mt-8">
                        <label for="ff-source-text" class="sr-only">Ministry source text</label>
                        <textarea
                            id="ff-source-text"
                            wire:model="sourceText"
                            rows="14"
                            class="ff-focus w-full rounded-[12px] border bg-white/90 p-4 text-base leading-relaxed backdrop-blur-sm"
                            style="border-color: var(--ff-border); color: var(--ff-text)"
                            placeholder="Paste or write your sermon notes, manuscript, or teaching material here…"
                        ></textarea>
                        @error('sourceText')
                            <p class="mt-2 text-sm" style="color: var(--ff-danger)" role="alert">{{ $message }}</p>
                        @enderror

                        <div class="mt-4 flex flex-col-reverse items-start gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-sm" style="color: var(--ff-text-muted)">
                                Nothing is generated or published automatically — you choose what happens next.
                            </p>
                            <button
                                type="submit"
                                class="ff-btn-primary w-full px-6 py-3 text-sm font-medium sm:w-auto"
                                wire:loading.attr="disabled"
                                wire:target="createSource"
                            >
                                <span wire:loading.remove wire:target="createSource">Continue</span>
                                <span wire:loading wire:target="createSource">Saving…</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            @if ($this->recentRuns->isNotEmpty())
                <div class="mt-8">
                    <p class="ff-eyebrow">Recent sources</p>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($this->recentRuns as $recent)
                            <a
                                href="{{ static::getUrl(['run' => $recent->id]) }}"
                                wire:navigate
                                class="ff-workspace-surface ff-focus block p-4 transition-colors hover:border-[var(--ff-teal)]"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <x-filament::badge :color="$runStatusColor($recent->status)">
                                        {{ $recent->status->label() }}
                                    </x-filament::badge>
                                    <span class="text-xs" style="color: var(--ff-text-muted)">
                                        {{ $recent->created_at->diffForHumans() }}
                                    </span>
                                </div>
                                <p class="mt-2 line-clamp-2 text-sm" style="color: var(--ff-text)">
                                    {{ \Illuminate\Support\Str::limit($recent->source_text, 140) }}
                                </p>
                            </a>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="mt-8">
                    <x-filament::empty-state
                        icon="heroicon-o-document-magnifying-glass"
                        heading="No sources yet"
                        description="Once you bring in your first sermon or teaching notes, it will appear here so you can pick up where you left off."
                    />
                </div>
            @endif
        @else
            {{-- ================= EXISTING RUN WORKSPACE ================= --}}
            <div @if ($this->isProcessing()) wire:poll.3s="poll" @endif class="space-y-6">
                @if ($showCelebration)
                    @include('filament.components.faithflow.celebration')
                @endif

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <a href="{{ static::getUrl() }}" wire:navigate class="ff-focus text-sm font-medium" style="color: var(--ff-teal)">
                        &larr; New source
                    </a>
                    <x-filament::badge :color="$runStatusColor($currentRun->status)">
                        {{ $currentRun->status->label() }}
                    </x-filament::badge>
                </div>

                {{-- Source (collapsed once analyzed — understanding, not the raw text, becomes primary) --}}
                <details class="ff-workspace-surface p-4" @if ($currentRun->status === FaithFlowRunStatus::DRAFT || $currentRun->status === FaithFlowRunStatus::ANALYSIS_FAILED) open @endif>
                    <summary class="cursor-pointer text-sm font-medium" style="color: var(--ff-teal)">
                        Your source ({{ number_format($currentRun->source_char_count) }} characters)
                    </summary>
                    <p class="mt-3 max-h-64 overflow-y-auto whitespace-pre-line text-sm leading-relaxed" style="color: var(--ff-text)">
                        {{ $currentRun->source_text }}
                    </p>
                </details>

                @if ($currentRun->status === FaithFlowRunStatus::DRAFT)
                    <div class="ff-atmosphere ff-workspace-surface relative overflow-hidden p-8 text-center">
                        @include('filament.components.faithflow.forms', ['variant' => 'minimal'])
                        <div class="relative">
                            <p class="text-sm" style="color: var(--ff-text-muted)">
                                Ready when you are. Analysis usually takes under a minute.
                            </p>
                            <button
                                wire:click="analyze"
                                class="ff-btn-primary mt-4 px-6 py-2.5 text-sm font-medium"
                                wire:loading.attr="disabled"
                                wire:target="analyze"
                            >
                                Analyze Source
                            </button>
                        </div>
                    </div>
                @endif

                @if ($currentRun->status === FaithFlowRunStatus::ANALYZING)
                    {{-- ================= ANALYSIS PROGRESS — Level A, Keryon Progress (§14/§15) ================= --}}
                    <div class="ff-atmosphere ff-workspace-surface relative overflow-hidden p-10 text-center" role="status" aria-live="polite">
                        @include('filament.components.faithflow.forms', ['variant' => 'full', 'breathe' => true])
                        <span class="ff-light-bloom ff-light-bloom--glow h-40 w-40" style="top: 10%; left: 50%; transform: translateX(-50%);" aria-hidden="true"></span>

                        <div class="relative">
                            <x-filament::loading-indicator class="mx-auto h-8 w-8" style="color: var(--ff-teal)" />
                            <p class="mt-4 ff-section-title">Reading your source…</p>

                            <ul class="mx-auto mt-4 flex max-w-xs flex-col items-start gap-2 text-left text-sm" style="color: var(--ff-text-muted)">
                                <li class="ff-stage">Preparing your source</li>
                                <li class="ff-stage">Understanding the main message</li>
                                <li class="ff-stage">Identifying themes and key points</li>
                                <li class="ff-stage">Preparing communication opportunities</li>
                            </ul>

                            <p class="mx-auto mt-4 max-w-md text-xs" style="color: var(--ff-text-muted)">
                                This page will update automatically — no need to keep it open.
                            </p>
                        </div>
                    </div>
                @endif

                @if ($currentRun->status === FaithFlowRunStatus::ANALYSIS_FAILED)
                    <div class="ff-workspace-surface p-6" role="alert">
                        <p class="font-medium" style="color: var(--ff-danger)">Analysis didn't complete.</p>
                        <p class="mt-1 text-sm" style="color: var(--ff-text-muted)">
                            Your source text is safe and hasn't been lost. You can try again.
                        </p>
                        <button
                            wire:click="analyze"
                            class="ff-btn-primary mt-4 px-5 py-2 text-sm font-medium"
                            wire:loading.attr="disabled"
                            wire:target="analyze"
                        >
                            Retry Analysis
                        </button>
                    </div>
                @endif

                @if ($currentRun->status === FaithFlowRunStatus::ANALYZED && $currentRun->canonical_analysis)
                    {{-- ================= UNDERSTANDING — analysis, not generated content (§16) ================= --}}
                    @php $analysis = $currentRun->canonical_analysis; @endphp
                    <div class="ff-workspace-surface relative overflow-hidden p-6" style="border-color: var(--ff-teal-100)">
                        <span class="ff-light-bloom h-24 w-24" style="top: -2rem; right: -2rem;" aria-hidden="true"></span>
                        <div class="relative">
                            <p class="ff-eyebrow">Understanding</p>
                            <h3 class="ff-section-title mt-1">Understanding your source</h3>
                            <div class="mt-4 grid gap-5 sm:grid-cols-2">
                                <div>
                                    <h4 class="ff-eyebrow">Central message</h4>
                                    <p class="mt-1 text-sm" style="color: var(--ff-text)">{{ $analysis['principal_message'] ?? '—' }}</p>
                                </div>
                                <div>
                                    <h4 class="ff-eyebrow">Summary</h4>
                                    <p class="mt-1 text-sm" style="color: var(--ff-text)">{{ $analysis['source_summary'] ?? '—' }}</p>
                                </div>
                                <div>
                                    <h4 class="ff-eyebrow">Key themes</h4>
                                    <ul class="mt-1 flex flex-wrap gap-2">
                                        @foreach (($analysis['key_themes'] ?? []) as $theme)
                                            <li class="ff-accent-badge px-2.5 py-1 text-xs font-medium">{{ $theme }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                                <div>
                                    <h4 class="ff-eyebrow">Ministry context</h4>
                                    <p class="mt-1 text-sm" style="color: var(--ff-text)">{{ $analysis['ministry_context'] ?? '—' }}</p>
                                </div>
                                @if (! empty($analysis['scripture_references']))
                                    <div class="sm:col-span-2">
                                        <h4 class="ff-eyebrow">Scripture references</h4>
                                        <ul class="mt-1 space-y-1 text-sm" style="color: var(--ff-text)">
                                            @foreach ($analysis['scripture_references'] as $ref)
                                                <li><span class="font-medium">{{ $ref['reference'] ?? '' }}</span> — {{ $ref['context'] ?? '' }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- ================= OUTPUT SELECTION — workspace tier ================= --}}
                    <div class="ff-workspace-surface p-6">
                        <p class="ff-eyebrow">Create</p>
                        <h3 class="ff-section-title mt-1">What would you like to create?</h3>
                        <fieldset class="mt-4 grid gap-3 sm:grid-cols-2">
                            <legend class="sr-only">Select the outputs you want FaithFlow to create</legend>
                            @foreach (FaithFlowOutputType::cases() as $type)
                                <label class="ff-focus flex cursor-pointer items-start gap-3 rounded-[12px] border p-3" style="border-color: var(--ff-border)">
                                    <input
                                        type="checkbox"
                                        wire:model="selectedOutputTypes"
                                        value="{{ $type->value }}"
                                        class="mt-0.5 rounded"
                                        style="accent-color: var(--ff-teal)"
                                    />
                                    <span>
                                        <span class="block text-sm font-medium" style="color: var(--ff-text)">{{ $type->label() }}</span>
                                        <span class="block text-xs" style="color: var(--ff-text-muted)">{{ $outputDescriptions[$type->value] }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </fieldset>
                        <button
                            wire:click="generateSelected"
                            class="ff-btn-primary mt-5 w-full px-5 py-2.5 text-sm font-medium sm:w-auto"
                            wire:loading.attr="disabled"
                            wire:target="generateSelected"
                        >
                            Generate Selected
                        </button>
                    </div>

                    @if ($currentRun->outputs->isNotEmpty())
                        {{-- ================= REVIEW WORKSPACE — deliberately quieter (§16) ================= --}}
                        <div class="ff-workspace-surface overflow-hidden lg:flex">
                            {{-- Mobile: dropdown navigation, avoids horizontal-tab overflow --}}
                            <div class="border-b p-4 lg:hidden" style="border-color: var(--ff-border)">
                                <label for="ff-output-select" class="sr-only">Choose an output to review</label>
                                <select
                                    id="ff-output-select"
                                    wire:model.live="activeOutputId"
                                    class="ff-focus w-full rounded-[12px] border p-2 text-sm"
                                    style="border-color: var(--ff-border)"
                                >
                                    @foreach ($currentRun->outputs as $output)
                                        <option value="{{ $output->id }}">{{ $output->output_type->label() }} — {{ $output->status->label() }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Desktop: left rail — a quiet left-border indicator, not a solid fill --}}
                            <nav class="hidden w-64 shrink-0 border-r p-3 lg:block" style="border-color: var(--ff-border)" aria-label="FaithFlow outputs">
                                <ul class="space-y-1">
                                    @foreach ($currentRun->outputs as $output)
                                        <li>
                                            <button
                                                type="button"
                                                wire:click="selectOutput({{ $output->id }})"
                                                class="ff-focus flex w-full items-center justify-between rounded-[10px] border-l-[3px] px-3 py-2 text-left text-sm"
                                                style="{{ $activeOutputId === $output->id ? 'border-color: var(--ff-amber); background: var(--ff-teal-50); color: var(--ff-teal); font-weight: 600;' : 'border-color: transparent; color: var(--ff-text)' }}"
                                                aria-current="{{ $activeOutputId === $output->id ? 'true' : 'false' }}"
                                            >
                                                <span>{{ $output->output_type->label() }}</span>
                                                <x-filament::badge :color="$outputStatusColor($output->status)" size="xs">
                                                    {{ $output->status->label() }}
                                                </x-filament::badge>
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            </nav>

                            {{-- Active output detail --}}
                            <div class="flex-1 p-6">
                                @php $active = $currentRun->outputs->firstWhere('id', $activeOutputId) ?? $currentRun->outputs->first(); @endphp

                                @if ($active)
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <h3 class="ff-section-title">{{ $active->output_type->label() }}</h3>
                                        <x-filament::badge :color="$outputStatusColor($active->status)">{{ $active->status->label() }}</x-filament::badge>
                                    </div>

                                    @if ($active->status === FaithFlowOutputStatus::PENDING || $active->status === FaithFlowOutputStatus::GENERATING)
                                        <div class="ff-atmosphere ff-workspace-surface relative mt-4 overflow-hidden p-8 text-center" role="status" aria-live="polite">
                                            @include('filament.components.faithflow.forms', ['variant' => 'minimal', 'breathe' => true])
                                            <div class="relative">
                                                <x-filament::loading-indicator class="mx-auto h-6 w-6" style="color: var(--ff-teal)" />
                                                <p class="mt-3 text-sm" style="color: var(--ff-text-muted)">Generating…</p>
                                            </div>
                                        </div>
                                    @elseif ($active->status === FaithFlowOutputStatus::FAILED)
                                        <div class="mt-6" role="alert">
                                            <p class="text-sm" style="color: var(--ff-danger)">Generation failed. Your other outputs are unaffected.</p>
                                            <button
                                                wire:click="retryOutput({{ $active->id }})"
                                                class="ff-btn-primary mt-3 px-4 py-2 text-sm font-medium"
                                                wire:loading.attr="disabled"
                                            >
                                                Retry
                                            </button>
                                        </div>
                                    @else
                                        {{-- GENERATED or APPROVED — the reading moment, calmer material --}}
                                        @if ($editingOutputId === $active->id)
                                            <div class="mt-4">
                                                <label for="ff-edit-{{ $active->id }}" class="sr-only">Edit {{ $active->output_type->label() }}</label>
                                                <textarea
                                                    id="ff-edit-{{ $active->id }}"
                                                    wire:model="editingContent"
                                                    rows="12"
                                                    class="ff-focus w-full rounded-[12px] border p-4 text-sm leading-relaxed"
                                                    style="border-color: var(--ff-border)"
                                                ></textarea>
                                                <div class="mt-3 flex flex-wrap gap-2">
                                                    <button wire:click="saveEdit({{ $active->id }})" class="ff-btn-primary px-4 py-2 text-sm font-medium" wire:loading.attr="disabled">
                                                        Save Changes
                                                    </button>
                                                    <button wire:click="cancelEditing" class="ff-btn-secondary px-4 py-2 text-sm font-medium">
                                                        Cancel
                                                    </button>
                                                </div>
                                            </div>
                                        @else
                                            <div class="ff-reading-surface ff-focus mt-4 max-h-[28rem] overflow-y-auto p-5" tabindex="0">
                                                <p class="ff-body-copy whitespace-pre-line">{{ $active->content }}</p>
                                            </div>

                                            @if ($active->isEdited())
                                                <p class="mt-2 text-xs" style="color: var(--ff-text-muted)">You've edited this output.</p>
                                            @endif

                                            @if ($active->status === FaithFlowOutputStatus::GENERATED)
                                                <div class="mt-4 flex flex-wrap gap-2">
                                                    <button wire:click="startEditing({{ $active->id }})" class="ff-btn-secondary px-4 py-2 text-sm font-medium">
                                                        Edit
                                                    </button>
                                                    <button
                                                        wire:click="regenerateOutput({{ $active->id }})"
                                                        wire:confirm="A new generated version will be created. {{ $active->isEdited() ? 'Your edits will stay intact.' : '' }}"
                                                        class="ff-btn-secondary px-4 py-2 text-sm font-medium"
                                                    >
                                                        Regenerate
                                                    </button>
                                                    <button wire:click="approveOutput({{ $active->id }})" class="ff-btn-primary px-4 py-2 text-sm font-medium">
                                                        @if ($active->output_type->contentType() !== null)
                                                            Approve &amp; Send to Content Studio
                                                        @else
                                                            Mark Reviewed
                                                        @endif
                                                    </button>
                                                </div>
                                            @else
                                                {{-- APPROVED — read-only provenance --}}
                                                <div class="ff-accent-badge mt-4 inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium">
                                                    <x-filament::icon icon="heroicon-o-lock-closed" class="h-3.5 w-3.5" />
                                                    Approved — this is now read-only history
                                                </div>
                                                @if ($active->contentItem)
                                                    <p class="mt-3 text-sm" style="color: var(--ff-text)">
                                                        This became a Content Studio draft:
                                                        <span class="font-medium">{{ $active->contentItem->title }}</span>
                                                    </p>
                                                @endif
                                            @endif
                                        @endif
                                    @endif
                                @endif
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>
