<?php

namespace App\Models;

use App\Campaigns\CampaignMediaManager;
use App\Enums\DesignPurpose;
use App\Enums\DesignState;
use App\Models\Concerns\BelongsToChurch;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Design extends Model
{
    use BelongsToChurch;

    protected $fillable = [
        'template_key',
        'template_version',
        'purpose',
        'variant',
        'inputs',
        'brand_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'template_version' => 'integer',
            'purpose' => DesignPurpose::class,
            'inputs' => 'array',
            'brand_snapshot' => 'array',
            'state' => DesignState::class,
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $design): void {
            $churchId = app(TenantContext::class)->currentChurchId() ?? $design->church_id;

            foreach ([
                [ContentItem::class, $design->content_item_id],
                [Campaign::class, $design->campaign_id],
                [CampaignCommunication::class, $design->campaign_communication_id],
                [MediaAsset::class, $design->primary_logo_media_id],
                [MediaAsset::class, $design->mark_media_id],
            ] as [$model, $id]) {
                if ($id === null) {
                    continue;
                }

                $record = $model::withoutGlobalScopes()->find($id);

                if ($record === null || $record->church_id !== $churchId) {
                    throw new LogicException('Every Design source and brand asset must belong to the same Church.');
                }
            }

            if ($design->campaign_communication_id !== null) {
                $communication = CampaignCommunication::withoutGlobalScopes()->find($design->campaign_communication_id);

                if ($design->campaign_id === null || $communication?->campaign_id !== $design->campaign_id) {
                    throw new LogicException('A Design Campaign communication must belong to its selected Campaign.');
                }
            }
        });
    }

    public function contentItem(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class)->withTrashed();
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class)->withTrashed();
    }

    public function campaignCommunication(): BelongsTo
    {
        return $this->belongsTo(CampaignCommunication::class)->withTrashed();
    }

    public function primaryLogo(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'primary_logo_media_id')->withTrashed();
    }

    public function mark(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'mark_media_id')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function mediaSelections(): HasMany
    {
        return $this->hasMany(DesignMedia::class);
    }

    public function outputs(): HasMany
    {
        return $this->hasMany(DesignOutput::class);
    }

    public function approve(User $user): void
    {
        $outputs = $this->outputs()->get();

        if ($outputs->isEmpty() || $outputs->contains(fn (DesignOutput $output): bool => ! $output->isRendered())) {
            throw new LogicException('A Design can only be approved after every requested output has rendered.');
        }

        $this->forceFill([
            'state' => DesignState::APPROVED,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ])->save();

        if ($this->campaign_id !== null) {
            $campaign = $this->campaign()->firstOrFail();

            foreach ($outputs as $output) {
                app(CampaignMediaManager::class)->attach($campaign, $output->mediaAsset()->firstOrFail(), 'Generated design');
            }
        }
    }
}
