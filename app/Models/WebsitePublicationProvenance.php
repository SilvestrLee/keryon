<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class WebsitePublicationProvenance extends Model
{
    use BelongsToChurch;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Website publication attribution is immutable.'));
        static::deleting(fn (): never => throw new LogicException('Website publication attribution cannot be deleted.'));
        static::creating(function (self $attribution): void {
            $publication = WebsitePublication::withoutGlobalScopes()->find($attribution->website_publication_id);
            $provenance = WebsiteContentProvenance::withoutGlobalScopes()->find($attribution->website_content_provenance_id);

            if ($publication === null || $provenance === null
                || $publication->church_id !== $attribution->church_id
                || $provenance->church_id !== $attribution->church_id) {
                throw new LogicException('Website publication attribution must remain within one Church.');
            }
        });
    }

    public function publication(): BelongsTo
    {
        return $this->belongsTo(WebsitePublication::class, 'website_publication_id');
    }

    public function provenance(): BelongsTo
    {
        return $this->belongsTo(WebsiteContentProvenance::class, 'website_content_provenance_id');
    }
}
