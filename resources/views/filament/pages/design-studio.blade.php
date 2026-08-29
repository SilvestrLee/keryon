@php
    use App\Enums\DesignOutputFormat;
    use App\Enums\DesignOutputStatus;
    use App\Enums\DesignState;

    $purposeOptions = $this->presenter->purposes();
    $template = $this->selectedTemplate;
    $brand = $this->brandProfile;
    $previewBrand = $brand ? app(App\Design\Brand\DesignBrandSnapshot::class)->from($brand) : app(App\Design\Brand\DesignBrandSnapshot::class)->from(null);
    $previewTitle = filled($inputs['title'] ?? null) ? $inputs['title'] : 'Sunday Encounter';
    $previewTheme = filled($inputs['theme'] ?? null) ? $inputs['theme'] : 'A place to belong';
    $previewDate = filled($inputs['date'] ?? null) ? $inputs['date'] : '2026-08-23';
    $previewTime = filled($inputs['time'] ?? null) ? $inputs['time'] : '09:30';
    $previewMediaId = (int) ($mediaBySlot['background'] ?? 0);
    $previewMedia = $previewMediaId ? $this->mediaAssets->firstWhere('id', $previewMediaId) : null;
@endphp

<x-filament-panels::page>
    <div class="ds" x-data="{}">
        @if ($currentDesign)
            @php($designState = $this->presenter->state($currentDesign))
            <section class="ds-review" aria-labelledby="design-review-title">
                <div class="ds-review-heading">
                    <div>
                        <a href="{{ static::getUrl() }}" wire:navigate class="ds-back-link">
                            <x-filament::icon icon="heroicon-o-arrow-left" class="h-4 w-4" />
                            Design Studio
                        </a>
                        <p class="ds-kicker">{{ $currentDesign->campaign ? 'Campaign design' : 'Church graphic' }}</p>
                        <h2 id="design-review-title" class="ds-display ds-review-title">{{ $currentDesign->inputs['title'] ?? 'Untitled design' }}</h2>
                        <div class="ds-status-row">
                            <span class="ds-state ds-state--{{ $designState['tone'] }}">{{ $designState['label'] }}</span>
                            <span>{{ $designState['description'] }}</span>
                        </div>
                    </div>

                    @if ($currentDesign->campaign)
                        <aside class="ds-context-card" aria-label="Campaign context">
                            <span>Created for Campaign</span>
                            <strong>{{ $currentDesign->campaign->title }}</strong>
                            @if ($currentDesign->campaignCommunication)
                                <small>{{ $currentDesign->campaignCommunication->title }}</small>
                            @endif
                            @if ($this->campaignWorkspaceUrl())
                                <a href="{{ $this->campaignWorkspaceUrl() }}" wire:navigate>Back to Campaign</a>
                            @endif
                        </aside>
                    @endif
                </div>

                <div class="ds-output-grid" aria-label="Generated formats">
                    @foreach ($currentDesign->outputs->sortBy(fn ($output) => array_search($output->format, DesignOutputFormat::cases(), true)) as $output)
                        @php($formatMeta = $this->presenter->format($output->format))
                        <article class="ds-output-card">
                            <div class="ds-output-visual ds-output-visual--{{ $output->format->value }}">
                                @if ($output->isRendered() && $output->mediaAsset)
                                    <img src="{{ $this->mediaUrl($output->mediaAsset) }}" alt="{{ $formatMeta['label'] }} preview of {{ $currentDesign->inputs['title'] ?? 'church design' }}">
                                @elseif ($output->status === DesignOutputStatus::FAILED)
                                    <div class="ds-output-message ds-output-message--failed">
                                        <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-7 w-7" />
                                        <strong>{{ $formatMeta['label'] }} couldn’t be created</strong>
                                        <span>Your other formats are safe.</span>
                                    </div>
                                @else
                                    <div class="ds-output-message" role="status">
                                        <span class="ds-pulse" aria-hidden="true"></span>
                                        <strong>Creating {{ $formatMeta['label'] }}…</strong>
                                    </div>
                                @endif
                            </div>
                            <div class="ds-output-meta">
                                <div>
                                    <h3>{{ $formatMeta['label'] }}</h3>
                                    <p>{{ $formatMeta['dimensions'] }}</p>
                                </div>
                                @if ($output->isRendered())
                                    <span class="ds-state ds-state--ready">Ready</span>
                                @elseif ($output->status === DesignOutputStatus::FAILED)
                                    <button type="button" wire:click="retryOutput({{ $output->id }})" wire:loading.attr="disabled" class="ds-btn ds-btn-secondary">
                                        <span wire:loading.remove wire:target="retryOutput({{ $output->id }})">Retry {{ $formatMeta['label'] }}</span>
                                        <span wire:loading wire:target="retryOutput({{ $output->id }})">Trying again…</span>
                                    </button>
                                @else
                                    <span class="ds-state ds-state--creating">Creating</span>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>

                <footer class="ds-review-actions">
                    <div>
                        <strong>{{ $currentDesign->outputs->where('status', DesignOutputStatus::RENDERED)->count() }} of {{ $currentDesign->outputs->count() }} formats ready</strong>
                        <p>Approval makes the completed graphics available through Institutional Media.</p>
                    </div>
                    @if ($currentDesign->state !== DesignState::APPROVED)
                        <button type="button" wire:click="approveDesign" class="ds-btn ds-btn-primary" @disabled(!$currentDesign->outputs->every->isRendered())>
                            Approve design
                        </button>
                    @else
                        <div class="ds-approved-note">
                            <x-filament::icon icon="heroicon-o-check-circle" class="h-5 w-5" />
                            Design approved, available in Media
                        </div>
                    @endif
                </footer>
            </section>
        @elseif ($create)
            <section class="ds-create" aria-labelledby="create-design-title">
                <header class="ds-create-header">
                    <div>
                        <a href="{{ static::getUrl() }}" wire:navigate class="ds-back-link">
                            <x-filament::icon icon="heroicon-o-arrow-left" class="h-4 w-4" />
                            Design Studio
                        </a>
                        <p class="ds-kicker">Guided creation</p>
                        <h2 id="create-design-title" class="ds-display">Create a design</h2>
                        <p>Keryon handles composition. You bring the message.</p>
                    </div>
                    <ol class="ds-steps" aria-label="Creation progress">
                        <li class="is-current"><span>1</span>Purpose</li>
                        <li><span>2</span>Content</li>
                        <li><span>3</span>Create</li>
                    </ol>
                </header>

                @if ($campaignCommunication)
                    <aside class="ds-campaign-banner" aria-label="Campaign context">
                        <x-filament::icon icon="heroicon-o-megaphone" class="h-6 w-6" />
                        <div>
                            <span>Creating for {{ $campaignCommunication->campaign->title }}</span>
                            <strong>{{ $campaignCommunication->title }}</strong>
                        </div>
                        <a href="{{ $this->campaignWorkspaceUrl() }}" wire:navigate>Back to Campaign</a>
                    </aside>
                @endif

                <div class="ds-create-layout">
                    <form wire:submit="createDesign" class="ds-controls">
                        <fieldset class="ds-section">
                            <legend>What are you creating?</legend>
                            <p class="ds-section-help">Choose the communication intent. Available templates respond to this choice.</p>
                            <div class="ds-purpose-grid">
                                @foreach ($purposeOptions as $value => $option)
                                    <button type="button" wire:click="choosePurpose('{{ $value }}')" class="ds-choice {{ $purpose === $value ? 'is-selected' : '' }}" aria-pressed="{{ $purpose === $value ? 'true' : 'false' }}">
                                        <strong>{{ $option['label'] }}</strong>
                                        <span>{{ $option['description'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </fieldset>

                        <fieldset class="ds-section">
                            <legend>Choose a template</legend>
                            <p class="ds-section-help">Only templates genuinely compatible with this purpose are shown.</p>
                            @if ($this->compatibleTemplates === [])
                                <div class="ds-inline-empty" role="status">
                                    <strong>No approved template yet</strong>
                                    <span>Keryon’s first template currently supports Sunday service graphics. Choose Sunday service to continue.</span>
                                </div>
                            @else
                                <div class="ds-template-grid">
                                    @foreach ($this->compatibleTemplates as $candidate)
                                        <button type="button" wire:click="selectTemplate('{{ $candidate->key }}', {{ $candidate->version }})" class="ds-template-card {{ $templateKey === $candidate->key && $templateVersion === $candidate->version ? 'is-selected' : '' }}" aria-pressed="{{ $templateKey === $candidate->key && $templateVersion === $candidate->version ? 'true' : 'false' }}">
                                            <span class="ds-template-art" aria-hidden="true"><i></i><b>Sunday<br>Encounter</b><em>23 AUG · 09:30</em></span>
                                            <span class="ds-template-copy">
                                                <strong>{{ $candidate->name }}</strong>
                                                <small>{{ $this->presenter->templateDescription($candidate) }}</small>
                                                <span>{{ collect($candidate->formats)->map(fn ($format) => $this->presenter->format($format)['label'])->join(' · ') }}</span>
                                            </span>
                                            <x-filament::icon icon="heroicon-o-check-circle" class="ds-template-check h-5 w-5" />
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                            @error('templateKey') <p class="ds-error" role="alert">{{ $message }}</p> @enderror
                        </fieldset>

                        @if ($template)
                            <fieldset class="ds-section">
                                <legend>Shape the message</legend>
                                <p class="ds-section-help">The template defines these fields and their composition limits.</p>
                                <div class="ds-fields">
                                    @foreach ($template->slots as $slot)
                                        @php($inputType = $this->presenter->slotInputType($slot))
                                        <label class="ds-field" for="design-slot-{{ $slot->key }}">
                                            <span>{{ $slot->label }} @if($slot->required)<b>Required</b>@endif</span>
                                            @if ($inputType === 'textarea')
                                                <textarea id="design-slot-{{ $slot->key }}" wire:model.live.debounce.300ms="inputs.{{ $slot->key }}" rows="4" @if($slot->maxCharacters) maxlength="{{ $slot->maxCharacters }}" @endif></textarea>
                                            @else
                                                <input id="design-slot-{{ $slot->key }}" type="{{ $inputType }}" wire:model.live.debounce.300ms="inputs.{{ $slot->key }}" @if($slot->maxCharacters) maxlength="{{ $slot->maxCharacters }}" @endif>
                                            @endif
                                            <small>
                                                @if($slot->maxCharacters)<span>{{ mb_strlen($inputs[$slot->key] ?? '') }} / {{ $slot->maxCharacters }} characters</span>@endif
                                                @if($slot->maxLines)<span>Up to {{ $slot->maxLines }} {{ Str::plural('line', $slot->maxLines) }}</span>@endif
                                            </small>
                                            @error('inputs.'.$slot->key) <em role="alert">{{ $message }}</em> @enderror
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>

                            @foreach ($template->imageSlots as $imageSlot)
                                <fieldset class="ds-section">
                                    <legend>{{ $imageSlot->label }}</legend>
                                    <p class="ds-section-help">Choose same-Church Institutional Media. {{ $imageSlot->minimumWidth }} × {{ $imageSlot->minimumHeight }} minimum.</p>
                                    @if ($this->mediaAssets->isEmpty())
                                        <div class="ds-inline-empty">
                                            <strong>No suitable media yet</strong>
                                            <span>You can continue without this optional image. Uploads remain available through existing Church media selectors.</span>
                                        </div>
                                    @else
                                        <div class="ds-media-grid" role="radiogroup" aria-label="{{ $imageSlot->label }}">
                                            <label class="ds-media-none {{ empty($mediaBySlot[$imageSlot->key]) ? 'is-selected' : '' }}">
                                                <input class="sr-only" type="radio" wire:model.live="mediaBySlot.{{ $imageSlot->key }}" value="">
                                                <x-filament::icon icon="heroicon-o-no-symbol" class="h-6 w-6" />
                                                <span>No image</span>
                                            </label>
                                            @foreach ($this->mediaAssets->filter(fn($asset) => $asset->width >= $imageSlot->minimumWidth && $asset->height >= $imageSlot->minimumHeight) as $asset)
                                                <label class="ds-media-option {{ (int)($mediaBySlot[$imageSlot->key] ?? 0) === $asset->id ? 'is-selected' : '' }}">
                                                    <input class="sr-only" type="radio" wire:model.live="mediaBySlot.{{ $imageSlot->key }}" value="{{ $asset->id }}">
                                                    <img src="{{ $this->mediaUrl($asset) }}" alt="{{ $asset->alt_text ?: $asset->original_filename }}">
                                                    <span>{{ Str::limit($asset->original_filename, 24) }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    @endif
                                    @error('media.'.$imageSlot->key) <p class="ds-error" role="alert">{{ $message }}</p> @enderror
                                </fieldset>
                            @endforeach

                            <fieldset class="ds-section">
                                <legend>Choose your formats</legend>
                                <p class="ds-section-help">Each format is composed independently for where it will be used.</p>
                                <div class="ds-format-grid">
                                    @foreach ($template->formats as $format)
                                        @php($formatMeta = $this->presenter->format($format))
                                        <label class="ds-format-option {{ in_array($format->value, $formats, true) ? 'is-selected' : '' }}">
                                            <input type="checkbox" wire:model.live="formats" value="{{ $format->value }}">
                                            <span class="ds-format-shape ds-format-shape--{{ $format->value }}" aria-hidden="true"></span>
                                            <strong>{{ $formatMeta['label'] }}</strong>
                                            <small>{{ $formatMeta['use'] }}<br>{{ $formatMeta['dimensions'] }}</small>
                                        </label>
                                    @endforeach
                                </div>
                                @error('formats') <p class="ds-error" role="alert">{{ $message }}</p> @enderror
                            </fieldset>

                            <aside class="ds-brand-card">
                                <div class="ds-brand-mark" style="--brand-primary: {{ $previewBrand['background'] }}; --brand-accent: {{ $previewBrand['accent'] }}"><i></i></div>
                                <div>
                                    <span>Your Brand</span>
                                    <strong>Church branding applied automatically</strong>
                                    <p>{{ $brand?->primary_logo_media_id ? 'Logo' : 'Safe identity fallback' }} · Primary colour · Accent · Typography</p>
                                </div>
                                <a href="{{ App\Filament\Clusters\Website\Pages\EditBrand::getUrl() }}" wire:navigate>Manage brand</a>
                            </aside>

                            <button type="submit" class="ds-btn ds-btn-primary ds-create-button" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="createDesign">Create design</span>
                                <span wire:loading wire:target="createDesign">Creating your design…</span>
                            </button>
                        @endif
                    </form>

                    <aside class="ds-preview-panel" aria-label="Live design preview">
                        <div class="ds-preview-toolbar">
                            <div>
                                <strong>Live preview</strong>
                                <span>Final PNGs are created only when you continue.</span>
                            </div>
                            <div class="ds-preview-tabs" role="group" aria-label="Preview format">
                                @foreach (DesignOutputFormat::cases() as $format)
                                    @if (in_array($format->value, $formats, true))
                                        <button type="button" wire:click="selectPreviewFormat('{{ $format->value }}')" aria-pressed="{{ $previewFormat === $format->value ? 'true' : 'false' }}" class="{{ $previewFormat === $format->value ? 'is-selected' : '' }}">{{ $this->presenter->format($format)['label'] }}</button>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                        <div class="ds-preview-stage">
                            <div class="ds-preview-frame ds-preview-frame--{{ $previewFormat }}" style="--preview-bg: {{ $previewBrand['background'] }}; --preview-text: {{ $previewBrand['primary_text'] }}; --preview-accent: {{ $previewBrand['accent'] }}; @if($previewMedia) --preview-image: url('{{ $this->mediaUrl($previewMedia) }}'); @endif">
                                <span class="ds-preview-church">{{ app(App\Support\TenantContext::class)->currentChurch()?->name }}</span>
                                <div class="ds-preview-content">
                                    <i></i>
                                    <h3>{{ $previewTitle }}</h3>
                                    <b>{{ $previewTheme }}</b>
                                    @if(filled($inputs['scripture'] ?? null))<p>{{ $inputs['scripture'] }}</p>@endif
                                    @if(filled($inputs['speaker'] ?? null))<p>{{ $inputs['speaker'] }}</p>@endif
                                </div>
                                <div class="ds-preview-footer"><span>{{ $previewDate }}</span><span>{{ $previewTime }}</span></div>
                                @if(filled($inputs['cta'] ?? null))<strong class="ds-preview-cta">{{ $inputs['cta'] }}</strong>@endif
                            </div>
                        </div>
                        <p class="ds-preview-note"><x-filament::icon icon="heroicon-o-shield-check" class="h-4 w-4" /> Preview uses the selected template contract and Church brand. Final rendering remains isolated.</p>
                    </aside>
                </div>
            </section>
        @else
            <section class="ds-landing" aria-labelledby="design-studio-title">
                <div class="ds-hero">
                    <div class="ds-hero-copy">
                        <p class="ds-kicker">Communications · Design Studio</p>
                        <h2 id="design-studio-title" class="ds-display">Church graphics,<br>without the guesswork.</h2>
                        <p>Choose an approved template, add your message, and let Keryon keep every format composed and on brand.</p>
                        <button type="button" wire:click="startCreating" class="ds-btn ds-btn-primary">Create a design</button>
                    </div>
                    <div class="ds-hero-art" aria-hidden="true">
                        <div class="ds-art-card ds-art-card--portrait"><span>GRACE COMMUNITY</span><b>Sunday<br>Encounter</b><i></i></div>
                        <div class="ds-art-card ds-art-card--square"><span>THIS SUNDAY</span><b>You belong<br>here.</b><i></i></div>
                    </div>
                </div>

                <section class="ds-design-paths" aria-labelledby="design-paths-title">
                    <div class="ds-section-heading">
                        <h3 id="design-paths-title">Choose your design path</h3>
                        <p>Generate finished artwork or download a professional source design.</p>
                    </div>
                    <div class="ds-design-path-grid">
                        <article class="ds-design-path ds-design-path--upcoming">
                            <x-filament::icon icon="heroicon-o-sparkles" class="h-7 w-7" />
                            <div>
                                <span>Coming later</span>
                                <h4>AI Design</h4>
                                <p>Generate finished church artwork from your communication.</p>
                            </div>
                            <span class="ds-design-path-status">Not available yet</span>
                        </article>
                        <a href="{{ App\Filament\Pages\DesignMarketplace::getUrl() }}" wire:navigate class="ds-design-path ds-design-path--marketplace">
                            <x-filament::icon icon="heroicon-o-shopping-bag" class="h-7 w-7" />
                            <div>
                                <span>Available now</span>
                                <h4>Design Marketplace</h4>
                                <p>Download professional PSD designs and edit them externally.</p>
                            </div>
                            <strong>Browse Marketplace <x-filament::icon icon="heroicon-o-arrow-right" class="h-4 w-4" /></strong>
                        </a>
                    </div>
                </section>

                <section class="ds-purpose-section" aria-labelledby="purpose-title">
                    <div class="ds-section-heading">
                        <h3 id="purpose-title">What are you creating?</h3>
                        <p>Start with the communication, not the dimensions.</p>
                    </div>
                    <div class="ds-purpose-landing-grid">
                        @foreach ($purposeOptions as $value => $option)
                            <button type="button" wire:click="startCreating('{{ $value }}')" class="ds-purpose-card">
                                <x-filament::icon :icon="match($value) { 'service' => 'heroicon-o-building-library', 'announcement' => 'heroicon-o-megaphone', 'scripture' => 'heroicon-o-book-open', 'quote' => 'heroicon-o-chat-bubble-bottom-center-text', default => 'heroicon-o-flag' }" class="h-6 w-6" />
                                <strong>{{ $option['label'] }}</strong>
                                <span>{{ $option['description'] }}</span>
                                <x-filament::icon icon="heroicon-o-arrow-up-right" class="ds-purpose-arrow h-5 w-5" />
                            </button>
                        @endforeach
                    </div>
                </section>

                <section class="ds-recent" aria-labelledby="recent-designs-title">
                    <div class="ds-section-heading">
                        <h3 id="recent-designs-title">Recent designs</h3>
                        <p>Your latest visual communication work.</p>
                    </div>
                    @if ($this->recentDesigns->isEmpty())
                        <div class="ds-empty">
                            <div class="ds-empty-art" aria-hidden="true"><i></i><b>A good<br>Sunday.</b></div>
                            <div>
                                <h4>Create your first church graphic</h4>
                                <p>Start with an approved Keryon template. Your Church branding will be applied automatically.</p>
                                <button type="button" wire:click="startCreating" class="ds-btn ds-btn-secondary">Choose a template</button>
                            </div>
                        </div>
                    @else
                        <div class="ds-recent-grid">
                            @foreach ($this->recentDesigns as $recent)
                                @php($recentState = $this->presenter->state($recent))
                                @php($thumbnail = $recent->outputs->first(fn($output) => $output->isRendered() && $output->mediaAsset)?->mediaAsset)
                                <a href="{{ static::getUrl(['design' => $recent->id]) }}" wire:navigate class="ds-recent-card">
                                    <span class="ds-recent-thumb">
                                        @if($thumbnail)<img src="{{ $this->mediaUrl($thumbnail) }}" alt="Preview of {{ $recent->inputs['title'] ?? 'church design' }}">@else<i></i><b>{{ Str::limit($recent->inputs['title'] ?? 'Church design', 28) }}</b>@endif
                                    </span>
                                    <span class="ds-recent-copy">
                                        <strong>{{ $recent->inputs['title'] ?? 'Untitled design' }}</strong>
                                        <small>{{ $recent->campaign?->title ?? $purposeOptions[$recent->purpose->value]['label'] ?? 'Church graphic' }}</small>
                                        <span class="ds-state ds-state--{{ $recentState['tone'] }}">{{ $recentState['label'] }}</span>
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </section>

                <aside class="ds-brand-strip">
                    <div class="ds-brand-mark" style="--brand-primary: {{ $previewBrand['background'] }}; --brand-accent: {{ $previewBrand['accent'] }}"><i></i></div>
                    <div><span>Your Brand</span><strong>Automatically applied to every design</strong><p>Approved colours and typography stay consistent without extra setup.</p></div>
                    <a href="{{ App\Filament\Clusters\Website\Pages\EditBrand::getUrl() }}" wire:navigate>Manage brand</a>
                </aside>
            </section>
        @endif
    </div>
</x-filament-panels::page>
