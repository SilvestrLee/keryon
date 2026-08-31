<?php

namespace App\Filament\Pages;

use App\Commercial\Entitlements\EntitlementResolver;
use App\Enums\AssetUse;
use App\Enums\Capability;
use App\Enums\ContentStatus;
use App\Enums\DesignState;
use App\Enums\EntitlementKey;
use App\Enums\WebsiteDraftDestination;
use App\Filament\Resources\ContentItemResource;
use App\Models\CampaignCommunication;
use App\Models\ContentItem;
use App\Models\DesignOutput;
use App\Models\MediaAsset;
use App\Models\WebsiteContentProvenance;
use App\Support\TenantContext;
use App\Trust\Rights\AssetRightsPolicy;
use App\Website\Drafts\ApplyApprovedContentToWebsiteDraft;
use App\Website\Drafts\AvailableWebsiteDraftDestinations;
use App\Website\Drafts\WebsiteDraftFingerprints;
use App\Website\Drafts\WebsiteDraftMapper;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;

class WebsiteDraftHandoff extends Page
{
    protected string $view = 'filament.pages.website-draft-handoff';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Use Content on Website';

    protected static ?string $slug = 'communications/website-draft/{content}';

    public ?int $sourceContentId = null;

    #[Url]
    public ?int $communication = null;

    public ?string $destination = null;

    public ?int $media = null;

    public bool $replace = false;

    public ?string $expectedFingerprint = null;

    public static function canAccess(): bool
    {
        $membership = app(TenantContext::class)->currentMembership();

        return $membership !== null
            && $membership->hasCapability(Capability::ContentView)
            && $membership->hasCapability(Capability::WebsiteContentManage);
    }

    public function mount(int $content): void
    {
        $this->sourceContentId = $content;
        $source = $this->source();
        Gate::authorize('view', $source);
        $tenant = app(TenantContext::class);
        $membership = $tenant->currentMembership();
        $church = $tenant->currentChurch();
        abort_unless($membership?->hasCapability(Capability::WebsiteContentManage) && $church !== null, 403);
        abort_unless(app(EntitlementResolver::class)->allows($church, EntitlementKey::WebsiteEnabled), 403);
        abort_unless($source->status === ContentStatus::APPROVED, 404);

        if ($this->communication !== null) {
            $valid = CampaignCommunication::query()->whereKey($this->communication)->where('content_item_id', $source->id)->exists();
            abort_unless($valid, 404);
        }
    }

    public function updatedDestination(): void
    {
        $this->replace = false;
        $this->media = null;
        $this->expectedFingerprint = $this->destinationState()['fingerprint'] ?? null;
    }

    public function apply(): mixed
    {
        $destination = WebsiteDraftDestination::tryFrom((string) $this->destination);
        if ($destination === null) {
            throw ValidationException::withMessages(['destination' => 'Choose a Website destination.']);
        }

        $result = app(ApplyApprovedContentToWebsiteDraft::class)->apply(
            $this->source(),
            $destination,
            $this->expectedFingerprint,
            $this->replace,
            $this->communication ? CampaignCommunication::query()->findOrFail($this->communication) : null,
            $this->media ? MediaAsset::query()->findOrFail($this->media) : null,
        );

        Notification::make()
            ->title($result->alreadyApplied ? 'This approved version is already applied.' : 'Website draft updated.')
            ->body('Review the working Website before publishing it.')
            ->success()->send();

        return redirect()->to($destination->editUrl());
    }

    /** @return array<string, mixed> */
    public function getViewData(): array
    {
        $source = $this->source();
        $destinations = app(AvailableWebsiteDraftDestinations::class)->for($source->content_type);
        $selected = WebsiteDraftDestination::tryFrom((string) $this->destination);
        $state = $selected ? $this->destinationState() : null;

        return [
            'source' => $source,
            'destinations' => $destinations,
            'selectedDestination' => $selected,
            'mapped' => $selected ? app(WebsiteDraftMapper::class)->map($source, $selected) : [],
            'destinationState' => $state,
            'eligibleMedia' => $selected?->supportsMedia() ? $this->eligibleMedia($source) : collect(),
            'contentUrl' => ContentItemResource::getUrl('view', ['record' => $source]),
        ];
    }

    private function source(): ContentItem
    {
        return ContentItem::query()->findOrFail($this->sourceContentId);
    }

    /** @return array{record: object, fingerprint: string, populated: bool, provenance: WebsiteContentProvenance|null} */
    private function destinationState(): array
    {
        $destination = WebsiteDraftDestination::from((string) $this->destination);
        $class = $destination->modelClass();
        $record = $class::query()->first() ?? new $class;
        $fingerprints = app(WebsiteDraftFingerprints::class);
        $mapper = app(WebsiteDraftMapper::class);

        return [
            'record' => $record,
            'fingerprint' => $fingerprints->destination($record, $destination, $mapper),
            'populated' => ! $fingerprints->empty($destination, $mapper, $record),
            'provenance' => $record->exists ? WebsiteContentProvenance::query()->where('destination', $destination->value)->where('website_record_id', $record->id)->latest('id')->with(['contentItem:id,title', 'campaign:id,title', 'actor:id,name'])->first() : null,
        ];
    }

    private function eligibleMedia(ContentItem $source)
    {
        return DesignOutput::query()
            ->whereHas('design', fn ($query) => $query->where('state', DesignState::APPROVED->value)
                ->where(fn ($sourceQuery) => $sourceQuery->where('content_item_id', $source->id)
                    ->when($this->communication, fn ($q) => $q->orWhere('campaign_communication_id', $this->communication))))
            ->whereNotNull('media_asset_id')
            ->with('mediaAsset.rights')
            ->get()->pluck('mediaAsset')->filter()
            ->filter(fn (MediaAsset $asset): bool => app(AssetRightsPolicy::class)->allows($asset, AssetUse::Publish))
            ->unique('id')->values();
    }
}
