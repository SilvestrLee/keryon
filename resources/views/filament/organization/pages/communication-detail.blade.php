<x-filament-panels::page>
    @php($communication = $this->communication())
    @php($revision = $this->revision())
    @php($isCampaign = $communication->kind->value === 'campaign')
    @php($canEdit = (bool) auth()->user()?->can('update', $communication))
    @php($canApprove = (bool) auth()->user()?->can('approve', $communication))
    @php($canClose = (bool) auth()->user()?->can('close', $communication))
    @php($canDelete = (bool) auth()->user()?->can('delete', $communication))
    @php($canViewDistributions = (bool) auth()->user()?->can('viewDistributions', $communication))
    @php($isDraft = $revision->state->value === 'draft')
    @php($isInReview = $revision->state->value === 'in_review')
    @php($isChangesRequested = $revision->state->value === 'changes_requested')
    @php($isApproved = in_array($revision->state->value, ['approved', 'distributed'], true))
    @include('filament.organization.partials.context')

    <div class="org-breadcrumbs">
        <a href="{{ $this->backUrl() }}" wire:navigate>← Communications</a>
    </div>

    <header class="org-page-heading org-page-heading--detail">
        <p class="org-eyebrow">{{ $isCampaign ? 'Shared campaign' : 'Communication' }} · v{{ $revision->version }}</p>
        <h1>{{ $revision->title }}</h1>
        <p>
            <span class="org-badge org-badge--{{ str($communication->state->value === 'closed' ? 'closed' : $revision->state->value)->replace('_', '-') }}">
                {{ $this->stateLabel($communication->state->value === 'closed' ? 'closed' : $revision->state->value) }}
            </span>
            &nbsp;·&nbsp;{{ $this->governingUnitPath() }} — prepared for Churches in this scope
        </p>
    </header>

    @if ($isChangesRequested && filled($revision->review_feedback))
        <section class="org-state-banner org-state-banner--changes-requested" role="status">
            <div>
                <strong>Changes requested</strong>
                <p>{{ $revision->review_feedback }}</p>
            </div>
            @if ($canEdit)
                <x-filament::button size="sm" wire:click="mountAction('resumeEditing')">Resume editing</x-filament::button>
            @endif
        </section>
    @elseif ($isApproved)
        <section class="org-state-banner org-state-banner--approved" role="status">
            <div>
                <strong>Approved and ready for sharing</strong>
                <p>Approved by {{ $revision->approverMembership?->user?->name ?? 'an Organization Administrator' }} on {{ $revision->approved_at?->format('j M Y') }}. This revision is read-only.</p>
            </div>
            <div class="org-actions">
                @if ($this->canDistribute())
                    <x-filament::button size="sm" wire:click="mountAction('distribute')">Distribute</x-filament::button>
                @endif
                @if ($canEdit)
                    <x-filament::button size="sm" color="gray" wire:click="mountAction('createNextRevision')">Create new revision</x-filament::button>
                @endif
            </div>
        </section>
    @endif

    <section class="org-section" aria-label="Communication workspace" x-data="{ tab: 'overview' }">
        <nav class="org-tabs" role="tablist" aria-label="Communication sections">
            <button type="button" class="org-tab" :class="tab === 'overview' && 'is-active'" @click="tab = 'overview'" role="tab">Overview</button>
            <button type="button" class="org-tab" :class="tab === 'resources' && 'is-active'" @click="tab = 'resources'" role="tab">Resources</button>
            <button type="button" class="org-tab" :class="tab === 'guidance' && 'is-active'" @click="tab = 'guidance'" role="tab">Guidance &amp; dates</button>
            <button type="button" class="org-tab" :class="tab === 'review' && 'is-active'" @click="tab = 'review'" role="tab">Review</button>
            @if ($canViewDistributions && $isApproved)
                <button type="button" class="org-tab" :class="tab === 'distribution' && 'is-active'" @click="tab = 'distribution'" role="tab">Distribution</button>
                <button type="button" class="org-tab" :class="tab === 'tracking' && 'is-active'" @click="tab = 'tracking'" role="tab">Tracking</button>
            @endif
            <button type="button" class="org-tab" :class="tab === 'history' && 'is-active'" @click="tab = 'history'" role="tab">History</button>
        </nav>

        {{-- Overview --}}
        <div class="org-tab-panel" x-show="tab === 'overview'" role="tabpanel">
            <dl class="org-detail-grid" style="grid-template-columns: repeat(2, minmax(0, 1fr)); padding: 0 0 1rem;">
                <div>
                    <dt>Purpose / summary</dt>
                    <dd>{{ $revision->summary ?: 'Not yet described.' }}</dd>
                </div>
                <div>
                    <dt>What should Churches do with this?</dt>
                    <dd>{{ $revision->requested_action ?: 'Not yet specified.' }}</dd>
                </div>
            </dl>
            @if ($canEdit && $isDraft)
                <x-filament::button size="sm" wire:click="mountAction('editOverview')">Edit overview</x-filament::button>
            @endif
        </div>

        {{-- Resources: materials + assets --}}
        <div class="org-tab-panel" x-show="tab === 'resources'" role="tabpanel" x-cloak>
            <div class="org-section__header org-section__header--compact" style="padding-left: 0; padding-right: 0;">
                <div>
                    <h3>Communication resources</h3>
                    <p>Structured copy Churches can use or adapt — announcements, captions, prayer points and more.</p>
                </div>
                @if ($canEdit && $isDraft)
                    <x-filament::button size="sm" wire:click="mountAction('addMaterial')">Add resource</x-filament::button>
                @endif
            </div>

            @if ($revision->materials->isEmpty())
                <div class="org-empty org-empty--compact">
                    <h3>No resources yet</h3>
                    <p>Add announcement copy, social captions, prayer points or other resources Churches can use.</p>
                </div>
            @else
                <div class="org-material-list">
                    @foreach ($revision->materials as $material)
                        <article class="org-material">
                            @if ($canEdit && $isDraft)
                                <div class="org-material__order">
                                    <button type="button" wire:click="moveMaterial({{ $material->id }}, 'up')" @if($loop->first) disabled @endif aria-label="Move {{ $material->title ?: $material->type->label() }} up">↑</button>
                                    <button type="button" wire:click="moveMaterial({{ $material->id }}, 'down')" @if($loop->last) disabled @endif aria-label="Move {{ $material->title ?: $material->type->label() }} down">↓</button>
                                </div>
                            @endif
                            <div class="org-material__body">
                                <div class="org-material__type">{{ $material->type->label() }}</div>
                                @if ($material->title)
                                    <div class="org-material__title">{{ $material->title }}</div>
                                @endif
                                <div class="org-material__excerpt">{{ \Illuminate\Support\Str::limit(strip_tags($material->body), 220) }}</div>
                            </div>
                            @if ($canEdit && $isDraft)
                                <div class="org-actions">
                                    <x-filament::button size="xs" color="gray" wire:click="mountAction('editMaterial', { material: {{ $material->id }} })">Edit</x-filament::button>
                                    <x-filament::button size="xs" color="danger" wire:click="mountAction('deleteMaterial', { material: {{ $material->id }} })">Delete</x-filament::button>
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif

            <div class="org-section__header org-section__header--compact" style="padding-left: 0; padding-right: 0; margin-top: 1.5rem; border-top: 1px solid var(--org-line); padding-top: 1.25rem;">
                <div>
                    <h3>Official assets</h3>
                    <p>Organization-owned flyers, artwork and documents with rights already recorded.</p>
                </div>
                @if ($canEdit && $isDraft)
                    <x-filament::button size="sm" wire:click="mountAction('addAsset')">Add official asset</x-filament::button>
                @endif
            </div>

            @if ($revision->assets->isEmpty())
                <div class="org-empty org-empty--compact">
                    <h3>No official assets yet</h3>
                    <p>Add a flyer, artwork or document Churches can use as provided.</p>
                </div>
            @else
                <div class="org-asset-grid">
                    @foreach ($revision->assets as $asset)
                        <article class="org-asset-card">
                            <div class="org-asset-card__meta">
                                <a class="org-asset-card__name org-row__link" href="{{ $this->assetUrl($asset) }}" target="_blank" rel="noopener">{{ $asset->original_filename }}</a>
                                <div class="org-asset-card__detail">
                                    {{ $asset->rights_basis->label() }}
                                    @if ($asset->attribution_required) · Attribution: {{ $asset->attribution_text }} @endif
                                    @if ($asset->alt_text) · Alt text: {{ $asset->alt_text }} @endif
                                </div>
                                @if ($asset->usage_guidance)
                                    <div class="org-asset-card__detail">{{ $asset->usage_guidance }}</div>
                                @endif
                            </div>
                            @if ($canEdit && $isDraft)
                                <x-filament::button size="xs" color="danger" wire:click="mountAction('deleteAsset', { asset: {{ $asset->id }} })">Delete</x-filament::button>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Guidance & dates --}}
        <div class="org-tab-panel" x-show="tab === 'guidance'" role="tabpanel" x-cloak>
            <dl class="org-detail-grid" style="grid-template-columns: repeat(2, minmax(0, 1fr)); padding: 0 0 1rem;">
                <div>
                    <dt>Local adaptation guidance</dt>
                    <dd>{{ $revision->adaptation_policy->label() }}</dd>
                    <p style="margin-top:.3rem; color: var(--org-muted); font-size: .76rem;">{{ $revision->adaptation_policy->helperText() }}</p>
                </div>
                @if ($isCampaign)
                    <div>
                        <dt>Campaign dates</dt>
                        <dd>{{ $revision->campaign_starts_on?->format('j M Y') ?? '—' }} to {{ $revision->campaign_ends_on?->format('j M Y') ?? '—' }}</dd>
                    </div>
                @endif
                <div>
                    <dt>Recommended response date</dt>
                    <dd>{{ $revision->recommended_response_on?->format('j M Y') ?? 'Not set' }}</dd>
                </div>
                <div>
                    <dt>Suggested publish-by date</dt>
                    <dd>{{ $revision->suggested_publish_by?->format('j M Y') ?? 'Not set' }}</dd>
                </div>
                <div>
                    <dt>Availability</dt>
                    <dd>
                        @if ($revision->available_from || $revision->available_until)
                            {{ $revision->available_from?->format('j M Y, H:i') ?? '—' }} to {{ $revision->available_until?->format('j M Y, H:i') ?? '—' }}
                        @else
                            Not set
                        @endif
                    </dd>
                </div>
            </dl>
            <p style="color: var(--org-muted); font-size: .72rem; margin-bottom: 1rem;">These are recommendations for Churches. No date automatically publishes or sends anything.</p>
            @if ($canEdit && $isDraft)
                <x-filament::button size="sm" wire:click="mountAction('editGuidance')">Edit guidance</x-filament::button>
            @endif
        </div>

        {{-- Review --}}
        <div class="org-tab-panel" x-show="tab === 'review'" role="tabpanel" x-cloak>
            <dl class="org-detail-grid" style="grid-template-columns: repeat(2, minmax(0, 1fr)); padding: 0 0 1rem;">
                <div><dt>Prepared by</dt><dd>{{ $revision->creatorMembership?->user?->name ?? '—' }}</dd></div>
                <div><dt>Submitted</dt><dd>{{ $revision->submitted_at?->format('j M Y, H:i') ?? 'Not yet submitted' }}</dd></div>
                <div><dt>Resources</dt><dd>{{ $revision->materials->count() }}</dd></div>
                <div><dt>Assets</dt><dd>{{ $revision->assets->count() }}</dd></div>
            </dl>

            <div class="org-form-actions" style="padding: 0 0 1rem; border-top: 0;">
                @if ($isDraft && $canEdit)
                    @if ($this->canSubmit())
                        <x-filament::button wire:click="mountAction('submitForReview')">Submit for review</x-filament::button>
                    @else
                        <p style="color: var(--org-muted); font-size: .78rem;">Add at least one communication resource before submitting for review.</p>
                    @endif
                @endif
                @if ($isInReview && $canApprove)
                    <x-filament::button color="success" wire:click="mountAction('approve')">Approve</x-filament::button>
                    <x-filament::button color="warning" wire:click="mountAction('requestChanges')">Request changes</x-filament::button>
                @endif
                @if ($isInReview && ! $canApprove)
                    <p style="color: var(--org-muted); font-size: .78rem;">Submitted and awaiting review by an authorized Organization Administrator.</p>
                @endif
            </div>
        </div>

        {{-- Distribution --}}
        @if ($canViewDistributions && $isApproved)
            <div class="org-tab-panel" x-show="tab === 'distribution'" role="tabpanel" x-cloak>
                <div class="org-section__header org-section__header--compact" style="padding-left: 0; padding-right: 0;">
                    <div>
                        <h3>Distribution</h3>
                        <p>Make this approved revision available to a bounded set of Churches. Churches remain responsible for local use.</p>
                    </div>
                    @if ($this->canDistribute())
                        <x-filament::button size="sm" wire:click="mountAction('distribute')">Distribute</x-filament::button>
                    @endif
                </div>

                @if ($this->distributions()->isEmpty())
                    <div class="org-empty org-empty--compact">
                        <h3>Not yet distributed</h3>
                        <p>{{ $this->canDistribute() ? 'Choose an audience to make this approved revision available to Churches.' : 'An authorized Organization Administrator can distribute this approved revision.' }}</p>
                    </div>
                @else
                    <div class="org-timeline">
                        @foreach ($this->distributions() as $distribution)
                            <div class="org-timeline-item">
                                <div>
                                    <div class="org-timeline-item__version">
                                        v{{ $distribution->revision->version }} · {{ $this->distributionTargetSummary($distribution) }}
                                        <span class="org-badge org-badge--{{ str($distribution->state->value)->replace('_', '-') }}">{{ $this->distributionStateLabel($distribution->state) }}</span>
                                    </div>
                                    <div class="org-timeline-item__meta">
                                        Requested by {{ $distribution->initiatorMembership?->user?->name ?? '—' }}, {{ $distribution->requested_at?->format('j M Y, H:i') }}
                                        @if ($distribution->snapshot_at)
                                            · Audience captured {{ $distribution->snapshot_at->format('j M Y, H:i') }}
                                        @endif
                                        @if ($distribution->state->value === 'completed')
                                            · Distributed to {{ $distribution->delivered_count }} {{ str('Church')->plural($distribution->delivered_count) }}
                                        @elseif ($distribution->state->value === 'failed')
                                            · {{ $distribution->failure_reason }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        {{-- Tracking (K-ORG-COMMS-001F) — read-only, factual delivery-outcome
             visibility only. Never Church-local content/state. --}}
        @if ($canViewDistributions && $isApproved)
            <div class="org-tab-panel" x-show="tab === 'tracking'" role="tabpanel" x-cloak>
                @php($trackingSummary = $this->trackingSummary())
                <div class="org-section__header org-section__header--compact" style="padding-left: 0; padding-right: 0;">
                    <div>
                        <h3>Tracking</h3>
                        <p>Which Churches received this, and their bounded response outcome. Local Church content, edits, and reviewers are never visible here.</p>
                    </div>
                </div>

                @if (empty($trackingSummary))
                    <div class="org-empty org-empty--compact">
                        <h3>This communication has not been distributed</h3>
                        <p>Tracking will appear once at least one distribution has been requested.</p>
                    </div>
                @else
                    <div class="org-tracking-summary">
                        @foreach ($trackingSummary as $revisionSummary)
                            <article class="org-tracking-revision">
                                <div class="org-tracking-revision__header">
                                    <h4>v{{ $revisionSummary->revisionVersion }}</h4>
                                    <span>
                                        {{ $revisionSummary->recipientCount }} {{ str('Church')->plural($revisionSummary->recipientCount) }}
                                        @if ($revisionSummary->firstDistributedAt)
                                            · Distributed {{ \Illuminate\Support\Carbon::parse($revisionSummary->firstDistributedAt)->format('j M Y') }}
                                        @endif
                                    </span>
                                </div>

                                @if ($revisionSummary->recipientCount === 0)
                                    <div class="org-empty org-empty--compact">
                                        <p>{{ $revisionSummary->recipientCount }} Churches received this communication. Responses have not been recorded yet.</p>
                                    </div>
                                @else
                                    <div class="org-tracking-counts">
                                        @foreach (['available', 'accepted', 'declined', 'imported', 'expired', 'withdrawn'] as $outcome)
                                            <span class="org-badge org-badge--{{ $outcome }}">{{ $this->outcomeLabel($outcome) }}: {{ $revisionSummary->count($outcome) }}</span>
                                        @endforeach
                                    </div>

                                    @if ($revisionSummary->count('declined') > 0 && count($revisionSummary->declineReasonCounts))
                                        <p class="org-tracking-decline-breakdown">
                                            Decline reasons:
                                            @foreach ($revisionSummary->declineReasonCounts as $code => $count)
                                                {{ $this->declineReasonLabel($code) }}: {{ $count }}@if (! $loop->last), @endif
                                            @endforeach
                                        </p>
                                    @endif
                                @endif

                                <div class="org-tracking-distributions">
                                    @foreach ($this->trackingDistributionsForRevision($revisionSummary->revisionId) as $distribution)
                                        <a href="#" wire:click.prevent="selectTrackingDistribution({{ $distribution->id }})">
                                            View Church list — {{ $this->distributionTargetSummary($distribution) }}
                                            ({{ $this->distributionStateLabel($distribution->state) }}) →
                                        </a>
                                    @endforeach
                                </div>
                            </article>
                        @endforeach
                    </div>

                    @if ($this->trackingDistributionId)
                        @php($distributionTracking = $this->selectedDistributionTracking())
                        @php($recipients = $this->trackingRecipients())
                        <section class="org-section" style="margin-top: 1.5rem;" aria-labelledby="tracking-recipients-heading">
                            <div class="org-section__header org-section__header--compact" style="padding-left: 0; padding-right: 0;">
                                <div>
                                    <h3 id="tracking-recipients-heading">Church list — v{{ $distributionTracking->revisionVersion }}</h3>
                                    <p>{{ $distributionTracking->targetSummary }} · {{ $distributionTracking->recipientCount }} {{ str('Church')->plural($distributionTracking->recipientCount) }}</p>
                                </div>
                            </div>

                            <div class="org-filters" role="search" aria-label="Filter Church list">
                                <label>
                                    <span>Search</span>
                                    <input type="search" wire:model.live.debounce.300ms="trackingSearch" placeholder="Church or Unit name">
                                </label>
                                <label>
                                    <span>Outcome</span>
                                    <select wire:model.live="trackingOutcomeFilter">
                                        <option value="">All</option>
                                        @foreach (['available', 'accepted', 'declined', 'imported', 'expired', 'withdrawn'] as $outcome)
                                            <option value="{{ $outcome }}">{{ $this->outcomeLabel($outcome) }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>

                            @if ($recipients->isEmpty())
                                <div class="org-empty org-empty--compact">
                                    <h3>No Churches match these tracking filters</h3>
                                    <p>Clear or adjust the filters to see other recipients.</p>
                                </div>
                            @else
                                <div style="overflow-x: auto;">
                                    <table class="org-recipient-table">
                                        <thead>
                                            <tr>
                                                <th>Church</th>
                                                <th>Outcome</th>
                                                <th>Shared</th>
                                                <th>Response</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($recipients as $row)
                                                <tr>
                                                    <td>
                                                        {{ $row->churchName }}
                                                        <small>{{ $row->snapshotUnitPath }}@if ($row->isDetached) · No longer assigned to this Organization @endif</small>
                                                    </td>
                                                    <td><span class="org-badge org-badge--{{ $row->outcome }}">{{ $row->outcomeLabel }}</span></td>
                                                    <td>{{ $row->availableAt ? \Illuminate\Support\Carbon::parse($row->availableAt)->format('j M Y') : '—' }}</td>
                                                    <td>
                                                        @if ($row->outcome === 'imported' && $row->importedAt)
                                                            Imported {{ \Illuminate\Support\Carbon::parse($row->importedAt)->format('j M Y') }}
                                                        @elseif ($row->respondedAt)
                                                            {{ $row->outcomeLabel }} {{ \Illuminate\Support\Carbon::parse($row->respondedAt)->format('j M Y') }}
                                                            @if ($row->declineReasonLabel)
                                                                <small>{{ $row->declineReasonLabel }}</small>
                                                            @endif
                                                        @else
                                                            —
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <div class="org-pagination">{{ $recipients->links() }}</div>
                            @endif
                        </section>
                    @endif
                @endif
            </div>
        @endif

        {{-- History --}}
        <div class="org-tab-panel" x-show="tab === 'history'" role="tabpanel" x-cloak>
            <h3 style="margin-bottom: .5rem;">Revision history</h3>
            <div class="org-timeline" style="margin-bottom: 1.5rem;">
                @foreach ($communication->revisions->reverse() as $historyRevision)
                    <div class="org-timeline-item">
                        <div>
                            <div class="org-timeline-item__version">v{{ $historyRevision->version }} — {{ $this->stateLabel($historyRevision->state->value) }}</div>
                            <div class="org-timeline-item__meta">
                                @if ($historyRevision->approved_at)
                                    Approved by {{ $historyRevision->approverMembership?->user?->name ?? '—' }}, {{ $historyRevision->approved_at->format('j M Y') }}
                                @else
                                    Created {{ $historyRevision->created_at->format('j M Y') }}
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <h3 style="margin-bottom: .5rem;">Governance activity</h3>
            @if ($this->auditEvents()->isEmpty())
                <div class="org-empty org-empty--compact">
                    <h3>No governance activity recorded yet</h3>
                </div>
            @else
                <div class="org-timeline">
                    @foreach ($this->auditEvents() as $event)
                        <div class="org-timeline-item">
                            <div>
                                <div class="org-timeline-item__version">{{ $event['label'] }}</div>
                                <div class="org-timeline-item__meta">{{ $event['actor'] ?? 'Organization member' }} · {{ \Illuminate\Support\Carbon::parse($event['occurred_at'])->format('j M Y, H:i') }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    @if ($canClose || ($canDelete && $isDraft && $communication->state->value === 'draft'))
        <section class="org-boundary-note">
            <strong>Communication controls</strong>
            <p>These affect the whole communication, not just this revision.</p>
            <div class="org-form-actions" style="padding: .75rem 0 0; border-top: 0;">
                @if ($canDelete && $communication->state->value === 'draft')
                    <x-filament::button size="sm" color="danger" wire:click="mountAction('deleteDraft')">Delete draft</x-filament::button>
                @endif
                @if ($canClose)
                    <x-filament::button size="sm" color="danger" wire:click="mountAction('close')">Close communication</x-filament::button>
                @endif
            </div>
        </section>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
