<?php

namespace App\Models;

use App\Enums\SocialPlatform;
use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;

/**
 * K-CHURCHWEB-001B §24 — institutional, not Website-owned (a social
 * handle describes the church regardless of whether Website exists —
 * see §22's ownership test). A public URL/handle record only, never an
 * OAuth-connected publishing account.
 */
class ChurchSocialLink extends Model
{
    use BelongsToChurch;

    protected $fillable = [
        'platform',
        'url',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'platform' => SocialPlatform::class,
            'sort_order' => 'integer',
        ];
    }
}
