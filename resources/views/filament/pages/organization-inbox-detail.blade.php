@php
    $delivery = $this->deliveryRecord();
    $revision = $delivery->revision;
    $communication = $revision?->communication;
    $state = $this->responseState();
@endphp

<x-filament-panels::page>
    <main class="kc-hub kc-inbox-detail">
        <a href="{{ $this->backUrl() }}" class="kc-text-link kc-back-link" wire:navigate>
            <span aria-hidden="true">←</span> Back to Organization Inbox
        </a>

        <header class="kc-inbox-detail-header">
            <div>
                <p class="kc-kicker">Shared by {{ $delivery->organization?->name ?? 'your Organization' }}</p>
                <h1>{{ $revision?->title ?? 'Shared communication' }}</h1>
                <p class="kc-inbox-detail-provenance">
                    {{ $communication?->kind?->value === 'campaign' ? 'Shared campaign' : 'Communication' }}
                    · Approved organization version v{{ $revision?->version ?? 1 }}
                </p>
            </div>
            <span class="kc-badge kc-badge--{{ str_replace('_', '-', $state) }}">{{ $this->responseStateLabel() }}</span>
        </header>

        @if ($this->isReferenceOnly())
            <section class="kc-inbox-notice" role="note">
                <p>This item is provided as reference material. Direct import into your Church workspace will not be available.</p>
            </section>
        @endif

        @if ($revision?->summary)
            <section class="kc-section" aria-labelledby="inbox-overview-heading">
                <div class="kc-section-heading"><div><h2 id="inbox-overview-heading">Overview</h2></div></div>
                <p class="kc-inbox-summary">{{ $revision->summary }}</p>
                @if ($revision->requested_action)
                    <p class="kc-inbox-summary"><strong>Requested action:</strong> {{ $revision->requested_action }}</p>
                @endif
            </section>
        @endif

        <section class="kc-section" aria-labelledby="inbox-resources-heading">
            <div class="kc-section-heading"><div><h2 id="inbox-resources-heading">Resources</h2><p>Guidance and content provided by your Organization.</p></div></div>

            @if ($revision && $revision->materials->isEmpty())
                <div class="kc-clear-state" role="status">
                    <div><h3>No written resources</h3><p>Your Organization did not include written material with this item.</p></div>
                </div>
            @else
                <div class="kc-inbox-material-list">
                    @foreach ($revision->materials as $material)
                        <article class="kc-inbox-material">
                            <div class="kc-inbox-material__type">{{ $material->type->label() }}</div>
                            @if ($material->title)
                                <h3>{{ $material->title }}</h3>
                            @endif
                            <div class="kc-inbox-material__body">{!! $this->materialBodyHtml($material) !!}</div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        @if ($revision && $revision->assets->isNotEmpty())
            <section class="kc-section" aria-labelledby="inbox-assets-heading">
                <div class="kc-section-heading"><div><h2 id="inbox-assets-heading">Assets</h2><p>Files provided by your Organization for this item.</p></div></div>
                <div class="kc-inbox-asset-list">
                    @foreach ($revision->assets as $asset)
                        <a class="kc-inbox-asset" href="{{ $this->assetUrl($asset) }}" target="_blank" rel="noopener">
                            <span aria-hidden="true"><x-filament::icon icon="heroicon-o-paper-clip" /></span>
                            <span>
                                <strong>{{ $asset->original_filename }}</strong>
                                @if ($asset->attribution_required && $asset->attribution_text)
                                    <small>Attribution: {{ $asset->attribution_text }}</small>
                                @endif
                            </span>
                            <x-filament::icon icon="heroicon-o-arrow-down-tray" aria-hidden="true" />
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="kc-section" aria-labelledby="inbox-guidance-heading">
            <div class="kc-section-heading"><div><h2 id="inbox-guidance-heading">Guidance &amp; dates</h2></div></div>
            <dl class="kc-inbox-dates">
                <div><dt>Shared</dt><dd>{{ $delivery->available_at?->format('j M Y') ?? '—' }}</dd></div>
                <div><dt>Available until</dt><dd>{{ $delivery->available_until?->format('j M Y') ?? 'No end date' }}</dd></div>
                @if ($revision?->recommended_response_on)
                    <div><dt>Recommended response by</dt><dd>{{ $revision->recommended_response_on->format('j M Y') }}</dd></div>
                @endif
                @if ($revision?->suggested_publish_by)
                    <div><dt>Suggested publish by</dt><dd>{{ $revision->suggested_publish_by->format('j M Y') }}</dd></div>
                @endif
                @if ($revision?->campaign_starts_on)
                    <div><dt>Campaign starts</dt><dd>{{ $revision->campaign_starts_on->format('j M Y') }}</dd></div>
                @endif
                @if ($revision?->campaign_ends_on)
                    <div><dt>Campaign ends</dt><dd>{{ $revision->campaign_ends_on->format('j M Y') }}</dd></div>
                @endif
                <div><dt>Local adaptation guidance</dt><dd>{{ $revision?->adaptation_policy?->label() ?? '—' }}</dd></div>
            </dl>
        </section>

        <section class="kc-section kc-inbox-response" aria-labelledby="inbox-response-heading">
            <div class="kc-section-heading"><div><h2 id="inbox-response-heading">Response</h2></div></div>

            @if ($state === 'available' || $state === 'expired' || $state === 'withdrawn')
                <div class="kc-inbox-response-body">
                    @if ($state === 'available')
                        <p>Your Church has not yet responded to this item.</p>
                        <div class="kc-inbox-response-actions">
                            <x-filament::button color="success" wire:click="mountAction('acceptDelivery')">Accept</x-filament::button>
                            <x-filament::button color="gray" wire:click="mountAction('declineDelivery')">Decline</x-filament::button>
                        </div>
                    @elseif ($state === 'expired')
                        <p>The response window for this item has closed. No further response can be recorded.</p>
                    @else
                        <p>The Organization has withdrawn this item.</p>
                    @endif
                </div>
            @elseif ($state === 'accepted')
                <div class="kc-inbox-response-body">
                    <p><strong>Accepted</strong> — Your Church has accepted this Organization communication.</p>
                    <p>Import into your Church workspace will become available in the next workflow step.</p>
                    @if ($delivery->responderMembership?->user)
                        <p class="kc-inbox-response-meta">Accepted by {{ $delivery->responderMembership->user->name }} on {{ $delivery->accepted_at?->format('j M Y') }}.</p>
                    @endif
                </div>
            @elseif ($state === 'declined')
                <div class="kc-inbox-response-body">
                    <p><strong>Declined</strong> — Your Church has declined this Organization communication.</p>
                    @if ($delivery->decline_reason_code)
                        <p class="kc-inbox-response-meta">Reason: {{ $delivery->decline_reason_code->label() }}</p>
                    @endif
                    @if ($delivery->responderMembership?->user)
                        <p class="kc-inbox-response-meta">Declined by {{ $delivery->responderMembership->user->name }} on {{ $delivery->declined_at?->format('j M Y') }}.</p>
                    @endif
                </div>
            @endif
        </section>

        <x-filament-actions::modals />
    </main>
</x-filament-panels::page>
