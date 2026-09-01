<?php

namespace App\Models;

use App\Enums\PlatformAuditEventType;
use App\Enums\PlatformAuditReasonCategory;
use App\Enums\PlatformAuditTargetType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

class PlatformAuditEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'uuid', 'platform_membership_id', 'actor_user_id', 'event_type',
        'target_type', 'target_id', 'previous_state', 'new_state',
        'reason_category', 'reason_note', 'correlation_id', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => PlatformAuditEventType::class,
            'target_type' => PlatformAuditTargetType::class,
            'reason_category' => PlatformAuditReasonCategory::class,
            'previous_state' => 'array',
            'new_state' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            $event->uuid ??= (string) Str::uuid();
            $event->correlation_id ??= (string) Str::uuid();
        });
        static::updating(fn (): never => throw new LogicException('Platform audit evidence is immutable.'));
        static::deleting(fn (): never => throw new LogicException('Platform audit evidence cannot be deleted.'));
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(PlatformMembership::class, 'platform_membership_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
