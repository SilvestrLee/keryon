<?php

namespace App\Models;

use App\Commercial\Entitlements\EntitlementValue;
use App\Enums\EntitlementKey;
use App\Enums\EntitlementValueType;
use App\Enums\PlanVersionStatus;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanVersionEntitlement extends Model
{
    protected $fillable = [
        'plan_version_id', 'entitlement_key', 'value_type',
        'boolean_value', 'integer_value',
    ];

    protected function casts(): array
    {
        return [
            'entitlement_key' => EntitlementKey::class,
            'value_type' => EntitlementValueType::class,
            'boolean_value' => 'boolean',
            'integer_value' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $entitlement): void {
            $entitlement->assertTypedValue();
            $entitlement->assertMutableVersion();
        });
        static::deleting(fn (self $entitlement) => $entitlement->assertMutableVersion());
    }

    public function planVersion(): BelongsTo
    {
        return $this->belongsTo(PlanVersion::class);
    }

    public function value(): EntitlementValue
    {
        return match ($this->value_type) {
            EntitlementValueType::BOOLEAN => EntitlementValue::boolean((bool) $this->boolean_value),
            EntitlementValueType::INTEGER => EntitlementValue::integer((int) $this->integer_value),
        };
    }

    private function assertMutableVersion(): void
    {
        $version = $this->relationLoaded('planVersion')
            ? $this->planVersion
            : PlanVersion::query()->find($this->plan_version_id);

        if ($version !== null && $version->status !== PlanVersionStatus::DRAFT) {
            throw new DomainException('Published or retired PlanVersion entitlements are immutable. Create a new version instead.');
        }
    }

    private function assertTypedValue(): void
    {
        if ($this->entitlement_key->valueType() !== $this->value_type) {
            throw new DomainException('Entitlement value type does not match its code-defined key.');
        }

        $valid = match ($this->value_type) {
            EntitlementValueType::BOOLEAN => $this->boolean_value !== null && $this->integer_value === null,
            EntitlementValueType::INTEGER => $this->integer_value !== null && $this->boolean_value === null,
        };

        if (! $valid) {
            throw new DomainException('Exactly one typed entitlement value must be present.');
        }
    }
}
