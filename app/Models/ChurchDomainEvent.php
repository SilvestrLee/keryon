<?php

namespace App\Models;

use App\Enums\ChurchDomainEventType;
use App\Enums\DomainFailureCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChurchDomainEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'church_id', 'church_domain_id', 'actor_user_id', 'actor_church_membership_id',
        'event_type', 'failure_code', 'correlation_id', 'occurred_at',
    ];

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new \LogicException('Church domain event evidence is immutable.'));
        static::deleting(fn (): never => throw new \LogicException('Church domain event evidence cannot be deleted.'));
    }

    protected function casts(): array
    {
        return [
            'event_type' => ChurchDomainEventType::class,
            'failure_code' => DomainFailureCode::class,
            'occurred_at' => 'immutable_datetime',
        ];
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(ChurchDomain::class, 'church_domain_id');
    }
}
