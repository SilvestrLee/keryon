<?php

namespace App\Filament\Pages;

use App\Design\Actions\CreateDesign;
use App\Design\Actions\RenderDesignOutput;
use App\Design\Actions\RetryDesignOutput;
use App\Design\Presentation\DesignStudioPresenter;
use App\Design\Templates\DesignTemplateDefinition;
use App\Design\Templates\DesignTemplateRegistry;
use App\Enums\DesignOutputFormat;
use App\Enums\DesignPurpose;
use App\Media\PrivateMediaDelivery;
use App\Models\CampaignCommunication;
use App\Models\ChurchBrandProfile;
use App\Models\Design;
use App\Models\DesignOutput;
use App\Models\MediaAsset;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use LogicException;
use ValueError;

class DesignStudio extends Page
{
    protected string $view = 'filament.pages.design-studio';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-swatch';

    protected static ?string $navigationLabel = 'Design';

    protected static string|\UnitEnum|null $navigationGroup = 'Communications';

    protected static ?string $title = 'Design Studio';

    protected static ?string $slug = 'design-studio/{design?}';

    protected static ?int $navigationSort = 4;

    public ?Design $currentDesign = null;

    #[Url]
    public bool $create = false;

    #[Url(as: 'campaign_communication')]
    public ?int $campaignCommunicationId = null;

    public ?CampaignCommunication $campaignCommunication = null;

    public string $purpose = 'service';

    public string $templateKey = 'sunday-modern-reference';

    public int $templateVersion = 1;

    public string $variant = 'default';

    /** @var array<string, string> */
    public array $inputs = ['title' => '', 'date' => '', 'time' => '', 'theme' => '', 'scripture' => '', 'speaker' => '', 'cta' => 'Join us this Sunday'];

    /** @var list<string> */
    public array $formats = ['square', 'portrait', 'story'];

    /** @var array<string, int|string|null> */
    public array $mediaBySlot = [];

    public string $previewFormat = 'square';

    public static function canAccess(): bool
    {
        return Auth::user()?->can('viewAny', Design::class) ?? false;
    }

    public function mount(?int $design = null): void
    {
        Gate::authorize('viewAny', Design::class);

        if ($design !== null) {
            $this->currentDesign = Design::query()->with(['outputs.mediaAsset', 'campaign', 'campaignCommunication'])->findOrFail($design);
            Gate::authorize('view', $this->currentDesign);
            $this->create = false;

            return;
        }

        if ($this->campaignCommunicationId !== null) {
            Gate::authorize('create', Design::class);
            $this->campaignCommunication = CampaignCommunication::query()->with('campaign')->findOrFail($this->campaignCommunicationId);
            Gate::authorize('view', $this->campaignCommunication);
            // Campaign is provenance, while visual purpose remains the
            // template-compatible communication intent. The initial curated
            // template is a service graphic.
            $this->purpose = DesignPurpose::SERVICE->value;
            $this->create = true;
            $this->inputs['title'] = $this->campaignCommunication->title;
        }
    }

    public function getRecentDesignsProperty(): Collection
    {
        return Design::query()->with(['outputs.mediaAsset', 'campaign'])->latest('updated_at')->limit(8)->get();
    }

    public function getMediaAssetsProperty(): Collection
    {
        return MediaAsset::query()
            ->whereIn('mime_type', ['image/jpeg', 'image/png', 'image/webp'])
            ->whereNotNull('width')
            ->whereNotNull('height')
            ->latest()
            ->limit(24)
            ->get();
    }

    public function getBrandProfileProperty(): ?ChurchBrandProfile
    {
        return ChurchBrandProfile::query()->with(['primaryLogo', 'mark'])->first();
    }

    /** @return list<DesignTemplateDefinition> */
    public function getCompatibleTemplatesProperty(): array
    {
        try {
            $purpose = DesignPurpose::from($this->purpose);
        } catch (ValueError) {
            return [];
        }

        return array_values(array_filter(
            app(DesignTemplateRegistry::class)->all(),
            fn (DesignTemplateDefinition $template): bool => collect($template->formats)
                ->contains(fn (DesignOutputFormat $format): bool => $template->supports($purpose, $format)),
        ));
    }

    public function getSelectedTemplateProperty(): ?DesignTemplateDefinition
    {
        return collect($this->compatibleTemplates)->first(
            fn (DesignTemplateDefinition $template): bool => $template->key === $this->templateKey && $template->version === $this->templateVersion,
        );
    }

    public function getPresenterProperty(): DesignStudioPresenter
    {
        return app(DesignStudioPresenter::class);
    }

    public function startCreating(?string $purpose = null): void
    {
        Gate::authorize('create', Design::class);

        if ($purpose !== null && isset($this->presenter->purposes()[$purpose])) {
            $this->purpose = $purpose;
        }

        $this->create = true;
    }

    public function choosePurpose(string $purpose): void
    {
        if (! isset($this->presenter->purposes()[$purpose])) {
            return;
        }

        $this->purpose = $purpose;
        $this->templateKey = $this->compatibleTemplates[0]->key ?? '';
        $this->templateVersion = $this->compatibleTemplates[0]->version ?? 1;
    }

    public function selectTemplate(string $key, int $version): void
    {
        $template = collect($this->compatibleTemplates)->first(
            fn (DesignTemplateDefinition $candidate): bool => $candidate->key === $key && $candidate->version === $version,
        );

        if ($template === null) {
            abort(404);
        }

        $this->templateKey = $template->key;
        $this->templateVersion = $template->version;
    }

    public function selectPreviewFormat(string $format): void
    {
        if (in_array($format, $this->formats, true) && DesignOutputFormat::tryFrom($format) !== null) {
            $this->previewFormat = $format;
        }
    }

    public function createDesign(): void
    {
        Gate::authorize('create', Design::class);
        $template = $this->selectedTemplate;

        if ($template === null) {
            throw ValidationException::withMessages(['templateKey' => 'Choose an available template for this purpose.']);
        }

        $formats = collect($this->formats)->map(function (string $format): DesignOutputFormat {
            return DesignOutputFormat::tryFrom($format)
                ?? throw ValidationException::withMessages(['formats' => 'Choose only supported output formats.']);
        })->all();

        $media = collect($this->mediaBySlot)
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): int => (int) $id)
            ->all();

        $campaign = $this->campaignCommunication?->campaign;
        $design = app(CreateDesign::class)->handle(
            templateKey: $template->key,
            templateVersion: $template->version,
            purpose: DesignPurpose::from($this->purpose),
            inputs: $this->inputs,
            formats: $formats,
            mediaBySlot: $media,
            variant: $this->variant,
            campaignId: $campaign?->id,
            campaignCommunicationId: $this->campaignCommunication?->id,
        );

        foreach ($design->outputs as $output) {
            app(RenderDesignOutput::class)->handle($output);
        }

        $this->redirect(static::getUrl(['design' => $design->id]), navigate: true);
    }

    public function retryOutput(int $outputId): void
    {
        $output = DesignOutput::query()->findOrFail($outputId);
        Gate::authorize('update', $output->design);

        try {
            app(RetryDesignOutput::class)->handle($output);
            app(RenderDesignOutput::class)->handle($output->fresh());
            $this->refreshDesign();
        } catch (LogicException) {
            Notification::make()->danger()->title('This format cannot be retried right now.')->send();
        }
    }

    public function approveDesign(): void
    {
        if ($this->currentDesign === null) {
            return;
        }

        Gate::authorize('approve', $this->currentDesign);

        try {
            $this->currentDesign->approve(Auth::user());
            $this->refreshDesign();
            Notification::make()->success()->title('Design approved')->body('Your graphics are now available in Media.')->send();
        } catch (LogicException) {
            Notification::make()->warning()->title('This design is not ready to approve.')->body('Retry any format that still needs attention.')->send();
        }
    }

    public function mediaUrl(MediaAsset $asset): string
    {
        Gate::authorize('view', $asset);

        return app(PrivateMediaDelivery::class)->url($asset);
    }

    public function campaignWorkspaceUrl(): ?string
    {
        $campaign = $this->campaignCommunication?->campaign ?? $this->currentDesign?->campaign;

        return $campaign ? CampaignWorkspace::getUrl(['campaign' => $campaign->id]) : null;
    }

    private function refreshDesign(): void
    {
        $this->currentDesign?->refresh();
        $this->currentDesign?->load(['outputs.mediaAsset', 'campaign', 'campaignCommunication']);
    }
}
