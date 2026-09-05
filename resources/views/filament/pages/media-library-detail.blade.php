@php
    $record = $this->record();
    $rights = $this->rightsSummary();
    $usage = $this->usage();
@endphp

<x-filament-panels::page>
    <main class="kc-hub kc-media-detail">
        <a href="{{ $this->backUrl() }}" class="kc-text-link kc-back-link" wire:navigate>
            <span aria-hidden="true">←</span> Back to Media Library
        </a>

        <header class="kc-inbox-detail-header">
            <div>
                <p class="kc-kicker">{{ $rights['provenance'] }}</p>
                <h1>{{ $record->original_filename }}</h1>
                <p class="kc-inbox-detail-provenance">{{ $this->humanFileType() }} · Added {{ $record->created_at->format('j M Y') }}</p>
            </div>
            <span class="kc-media-rights kc-media-rights--{{ str($rights['statusLabel'])->lower()->slug() }}">{{ $rights['statusLabel'] }}</span>
        </header>

        <div class="kc-media-detail-layout">
            <div class="kc-media-detail-preview">
                <img src="{{ $this->previewUrl() }}" alt="{{ $record->alt_text ?: '' }}">
            </div>

            <div class="kc-media-detail-facts">
                <section class="kc-section" aria-labelledby="media-detail-heading">
                    <div class="kc-section-heading"><div><h2 id="media-detail-heading">Details</h2></div></div>
                    <dl class="kc-media-fact-list">
                        <div class="kc-media-fact"><dt>Filename</dt><dd>{{ $record->original_filename }}</dd></div>
                        <div class="kc-media-fact"><dt>Type</dt><dd>{{ $this->humanFileType() }}</dd></div>
                        @if ($record->width && $record->height)
                            <div class="kc-media-fact"><dt>Dimensions</dt><dd>{{ $record->width }}×{{ $record->height }}</dd></div>
                        @endif
                        <div class="kc-media-fact"><dt>File size</dt><dd>{{ $this->humanFileSize() }}</dd></div>
                        <div class="kc-media-fact"><dt>Source</dt><dd>{{ $rights['provenance'] }}</dd></div>
                        <div class="kc-media-fact"><dt>Rights</dt><dd>{{ $rights['statusLabel'] }}</dd></div>
                        <div class="kc-media-fact"><dt>Allowed uses</dt><dd>{{ implode(', ', $rights['allowedUses']) ?: '—' }}</dd></div>
                        @if ($rights['attributionRequired'])
                            <div class="kc-media-fact"><dt>Attribution</dt><dd>Required{{ $rights['attributionText'] ? ' — '.$rights['attributionText'] : '' }}</dd></div>
                        @endif
                        <div class="kc-media-fact"><dt>Alt text</dt><dd>{{ $record->alt_text ?: 'Not set' }}</dd></div>
                        <div class="kc-media-fact"><dt>Added</dt><dd>{{ $record->created_at->format('j M Y') }}</dd></div>
                    </dl>
                </section>

                <section class="kc-section" aria-labelledby="media-usage-heading">
                    <div class="kc-section-heading"><div><h2 id="media-usage-heading">Where this is used</h2></div></div>
                    @if (empty($usage))
                        <div class="kc-clear-state" role="status">
                            <div><h3>Not currently used</h3><p>This asset is not referenced by Brand, any Campaign, Design, or your published Website right now.</p></div>
                        </div>
                    @else
                        <ul class="kc-media-usage-list">
                            @foreach ($usage as $line)
                                <li><x-filament::icon icon="heroicon-o-check-circle" aria-hidden="true" /> {{ $line }}</li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                <section class="kc-section" aria-labelledby="media-actions-heading">
                    <div class="kc-section-heading"><div><h2 id="media-actions-heading">Actions</h2></div></div>
                    <div class="kc-media-actions">
                        <x-filament::button tag="a" href="{{ $this->previewUrl() }}" target="_blank" color="gray" icon="heroicon-o-arrow-down-tray">Download</x-filament::button>
                        @if ($this->canManage())
                            <x-filament::button color="gray" icon="heroicon-o-pencil-square" wire:click="mountAction('editAltText')">Edit alt text</x-filament::button>
                            <x-filament::button color="danger" icon="heroicon-o-trash" wire:click="mountAction('deleteMedia')">Delete</x-filament::button>
                        @endif
                    </div>
                </section>
            </div>
        </div>

        <x-filament-actions::modals />
    </main>
</x-filament-panels::page>
