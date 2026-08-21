<?php

namespace App\Models;

use App\Enums\WebsiteTheme;
use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;

/**
 * K-CHURCHWEB-001B §19 ("Global") + §25 (theme-selection seam). One row
 * per Church. `theme` is a plain enum-backed string with zero
 * relationship to any Website Content table — selecting a theme can
 * never mutate content (see the report §14 for the Theme ≠ Content
 * evidence this model provides).
 */
class WebsiteSettings extends Model
{
    use BelongsToChurch;

    protected $fillable = [
        'theme',
        'footer_note',
    ];

    protected function casts(): array
    {
        return [
            'theme' => WebsiteTheme::class,
        ];
    }
}
