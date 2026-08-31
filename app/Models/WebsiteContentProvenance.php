<?php

namespace App\Models;

use App\Enums\WebsiteDraftDestination;
use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebsiteContentProvenance extends Model
{
    use BelongsToChurch;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'destination' => WebsiteDraftDestination::class,
            'source_approved_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
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

    public function design(): BelongsTo
    {
        return $this->belongsTo(Design::class);
    }

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class)->withTrashed();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public function publicationAttributions(): HasMany
    {
        return $this->hasMany(WebsitePublicationProvenance::class);
    }
}
