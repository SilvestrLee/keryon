<?php

namespace App\Models;

use App\Enums\CommunicationChannel;
use App\Enums\ContentStatus;
use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

class CampaignCommunication extends Model
{
    use BelongsToChurch;
    use SoftDeletes;

    public const READINESS_CANCELLED = 'cancelled';

    public const READINESS_NOT_STARTED = 'not_started';

    public const READINESS_IN_PREPARATION = 'in_preparation';

    public const READINESS_AWAITING_APPROVAL = 'awaiting_approval';

    public const READINESS_PREPARED = 'prepared';

    public const READINESS_OUTSTANDING = 'outstanding';

    protected $fillable = [
        'title',
        'purpose',
        'channel',
        'target_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'channel' => CommunicationChannel::class,
            'target_at' => 'datetime',
            'sort_order' => 'integer',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $communication): void {
            $campaign = Campaign::query()->find($communication->campaign_id);

            if ($campaign === null || $campaign->church_id !== $communication->church_id) {
                throw new LogicException('Campaign communication must belong to the active Church and its Campaign.');
            }

            if ($communication->content_item_id !== null) {
                $contentItem = ContentItem::withTrashed()->find($communication->content_item_id);

                if ($contentItem === null || $contentItem->church_id !== $communication->church_id) {
                    throw new LogicException('Campaign communication content must belong to the active Church.');
                }
            }
        });
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function contentItem(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class)->withTrashed();
    }

    public function designs(): HasMany
    {
        return $this->hasMany(Design::class);
    }

    public function websiteContentProvenances(): HasMany
    {
        return $this->hasMany(WebsiteContentProvenance::class);
    }

    public function supersededWebsiteContentProvenances(): HasMany
    {
        return $this->hasMany(WebsiteContentProvenance::class)
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('website_content_provenances as newer')
                    ->whereColumn('newer.church_id', 'website_content_provenances.church_id')
                    ->whereColumn('newer.destination', 'website_content_provenances.destination')
                    ->whereColumn('newer.website_record_id', 'website_content_provenances.website_record_id')
                    ->whereColumn('newer.id', '>', 'website_content_provenances.id');
            });
    }

    public function readiness(): string
    {
        if ($this->cancelled_at !== null) {
            return self::READINESS_CANCELLED;
        }

        $contentItem = $this->contentItem;

        if ($this->content_item_id === null) {
            return self::READINESS_NOT_STARTED;
        }

        if ($contentItem === null || $contentItem->trashed()) {
            return self::READINESS_OUTSTANDING;
        }

        return match ($contentItem->status) {
            ContentStatus::DRAFT, ContentStatus::REJECTED => self::READINESS_IN_PREPARATION,
            ContentStatus::REVIEW => self::READINESS_AWAITING_APPROVAL,
            ContentStatus::APPROVED => self::READINESS_PREPARED,
        };
    }
}
