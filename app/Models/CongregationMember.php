<?php

namespace App\Models;

use App\Enums\CongregationStatus;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class CongregationMember extends Model
{
    use BelongsToChurch;

    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'email',
        'birthday',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'birthday' => 'date',
            'status' => CongregationStatus::class,
        ];
    }

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => trim(collect([
                $this->first_name,
                $this->last_name,
            ])->filter()->implode(' ')),
        );
    }
}

