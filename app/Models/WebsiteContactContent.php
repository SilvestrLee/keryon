<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;

/**
 * K-CHURCHWEB-001B §19 ("Contact" section). One row per Church.
 * Deliberately thin — Address/Phone/Email live on `Church` after applying
 * the institutional-ownership test (§22); see the report §5.
 */
class WebsiteContactContent extends Model
{
    use BelongsToChurch;

    protected $fillable = [
        'office_hours',
        'map_embed_url',
    ];
}
