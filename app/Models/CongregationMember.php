<?php

namespace App\Models;

use App\Enums\CongregationStatus;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;

class CongregationMember extends Model
{
    use BelongsToChurch;

    protected $fillable = [
        'church_id',
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
}

