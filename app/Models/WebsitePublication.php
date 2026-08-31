<?php

namespace App\Models;

use App\Enums\MediaPublicConsumer;
use App\Enums\PublicationDestination;
use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class WebsitePublication extends Model
{
    use BelongsToChurch;

    protected $fillable = [
        'destination',
        'theme',
        'snapshot',
        'working_fingerprint',
        'previous_publication_id',
        'trust_evidence',
        'published_by',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'destination' => PublicationDestination::class,
            'snapshot' => 'array',
            'trust_evidence' => 'array',
            'published_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Website publication evidence is immutable.'));
        static::deleting(fn (): never => throw new LogicException('Website publication evidence cannot be deleted.'));
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function previousPublication(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_publication_id');
    }

    public function publicReferences(): HasMany
    {
        return $this->hasMany(MediaPublicReference::class, 'consumer_id')
            ->where('consumer_type', MediaPublicConsumer::WebsitePublication->value);
    }

    public function provenanceAttributions(): HasMany
    {
        return $this->hasMany(WebsitePublicationProvenance::class);
    }
}
