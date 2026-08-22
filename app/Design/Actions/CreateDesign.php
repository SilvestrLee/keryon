<?php

namespace App\Design\Actions;

use App\Design\Brand\DesignBrandSnapshot;
use App\Design\Templates\DesignImageSlot;
use App\Design\Templates\DesignTemplateRegistry;
use App\Enums\DesignOutputFormat;
use App\Enums\DesignOutputStatus;
use App\Enums\DesignPurpose;
use App\Enums\DesignState;
use App\Models\Campaign;
use App\Models\CampaignCommunication;
use App\Models\ChurchBrandProfile;
use App\Models\ContentItem;
use App\Models\Design;
use App\Models\MediaAsset;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;

class CreateDesign
{
    public function __construct(
        private readonly DesignTemplateRegistry $templates,
        private readonly DesignBrandSnapshot $brandSnapshot,
    ) {}

    /**
     * @param  array<string, mixed>  $inputs
     * @param  list<DesignOutputFormat>  $formats
     * @param  array<string, int>  $mediaBySlot
     */
    public function handle(
        string $templateKey,
        int $templateVersion,
        DesignPurpose $purpose,
        array $inputs,
        array $formats,
        array $mediaBySlot = [],
        string $variant = 'default',
        ?int $contentItemId = null,
        ?int $campaignId = null,
        ?int $campaignCommunicationId = null,
    ): Design {
        Gate::authorize('create', Design::class);

        $churchId = app(TenantContext::class)->currentChurchId()
            ?? throw new LogicException('A Design requires an active Church context.');
        $template = $this->templates->resolve($templateKey, $templateVersion);
        $formats = array_values(array_unique($formats, SORT_REGULAR));

        if ($formats === []) {
            throw ValidationException::withMessages(['formats' => 'Select at least one output format.']);
        }

        foreach ($formats as $format) {
            if (! $format instanceof DesignOutputFormat || ! $template->supports($purpose, $format, $variant)) {
                throw ValidationException::withMessages(['formats' => 'The template does not support the selected purpose, variant, or format.']);
            }
        }

        $normalizedInputs = $template->validateInputs($inputs);
        $contentItem = $this->owned(ContentItem::class, $contentItemId, $churchId, 'ContentItem');
        $campaign = $this->owned(Campaign::class, $campaignId, $churchId, 'Campaign');
        $communication = $this->owned(CampaignCommunication::class, $campaignCommunicationId, $churchId, 'CampaignCommunication');

        if ($communication !== null && ($campaign === null || $communication->campaign_id !== $campaign->id)) {
            throw ValidationException::withMessages(['campaign_communication_id' => 'The Campaign communication must belong to the selected Campaign.']);
        }

        if ($communication?->content_item_id !== null && $contentItem !== null && $communication->content_item_id !== $contentItem->id) {
            throw ValidationException::withMessages(['content_item_id' => 'The source ContentItem must match the Campaign communication.']);
        }

        $profile = ChurchBrandProfile::query()->first();
        $media = $this->resolveMedia($template->imageSlots, $mediaBySlot, $churchId);

        if ($template->brand->logoRequired && $profile?->primary_logo_media_id === null) {
            throw ValidationException::withMessages(['brand' => 'This template requires a primary Church logo.']);
        }

        $sortedFormats = collect($formats)->sortBy(fn (DesignOutputFormat $format): string => $format->value)->values();

        return DB::transaction(function () use (
            $template,
            $purpose,
            $variant,
            $normalizedInputs,
            $profile,
            $contentItem,
            $campaign,
            $communication,
            $media,
            $sortedFormats,
        ): Design {
            $design = new Design([
                'template_key' => $template->key,
                'template_version' => $template->version,
                'purpose' => $purpose,
                'variant' => $variant,
                'inputs' => $normalizedInputs,
                'brand_snapshot' => $this->brandSnapshot->from($profile),
            ]);
            $design->forceFill([
                'content_item_id' => $contentItem?->id,
                'campaign_id' => $campaign?->id,
                'campaign_communication_id' => $communication?->id,
                'primary_logo_media_id' => $profile?->primary_logo_media_id,
                'mark_media_id' => $profile?->mark_media_id,
                'created_by' => Auth::id(),
                'state' => DesignState::DRAFT,
            ])->save();

            foreach ($media as $slotKey => $asset) {
                $selection = $design->mediaSelections()->make(['slot_key' => $slotKey]);
                $selection->forceFill([
                    'church_id' => $design->church_id,
                    'media_asset_id' => $asset->id,
                ])->save();
            }

            foreach ($sortedFormats as $format) {
                $output = $design->outputs()->make(['format' => $format]);
                $output->forceFill([
                    'church_id' => $design->church_id,
                    'status' => DesignOutputStatus::PENDING,
                ])->save();
            }

            return $design->load(['mediaSelections.mediaAsset', 'outputs']);
        });
    }

    /**
     * @param  class-string  $model
     */
    private function owned(string $model, ?int $id, int $churchId, string $label): mixed
    {
        if ($id === null) {
            return null;
        }

        $record = $model::withoutGlobalScopes()->find($id);

        if ($record === null || $record->church_id !== $churchId) {
            throw ValidationException::withMessages([strtolower($label).'_id' => "The selected {$label} does not belong to the active Church."]);
        }

        return $record;
    }

    /**
     * @param  list<DesignImageSlot>  $slots
     * @param  array<string, int>  $selected
     * @return array<string, MediaAsset>
     */
    private function resolveMedia(array $slots, array $selected, int $churchId): array
    {
        $definitions = collect($slots)->keyBy('key');
        $unknown = array_diff(array_keys($selected), $definitions->keys()->all());

        if ($unknown !== []) {
            throw ValidationException::withMessages(['media' => 'Unknown image slots: '.implode(', ', $unknown).'.']);
        }

        $resolved = [];

        foreach ($definitions as $key => $slot) {
            $id = $selected[$key] ?? null;

            if ($id === null) {
                if ($slot->required) {
                    throw ValidationException::withMessages(["media.{$key}" => "The {$slot->label} slot is required."]);
                }

                continue;
            }

            $asset = MediaAsset::withoutGlobalScopes()->find($id);

            if ($asset === null || $asset->church_id !== $churchId) {
                throw ValidationException::withMessages(["media.{$key}" => 'The selected media does not belong to the active Church.']);
            }

            if (! str_starts_with($asset->mime_type, 'image/')) {
                throw ValidationException::withMessages(["media.{$key}" => 'Design image slots require an Institutional Media image.']);
            }

            if ($asset->width === null || $asset->height === null || $asset->width < $slot->minimumWidth || $asset->height < $slot->minimumHeight) {
                throw ValidationException::withMessages(["media.{$key}" => "The selected image must be at least {$slot->minimumWidth}x{$slot->minimumHeight} pixels."]);
            }

            $resolved[$key] = $asset;
        }

        ksort($resolved);

        return $resolved;
    }
}
