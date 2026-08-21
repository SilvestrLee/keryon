<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;

/**
 * K-CHURCHWEB-001B §19 ("About" section). One row per Church.
 */
class WebsiteAboutContent extends Model
{
    use BelongsToChurch;

    protected $fillable = [
        'church_story',
        'vision',
        'mission',
        'leadership_introduction',
    ];
}
