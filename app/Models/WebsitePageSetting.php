<?php

namespace App\Models;

use App\Enums\WebsitePageType;
use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;

/**
 * K-WEB-V1-001D-B §25 — bounded Church-level page configuration only
 * (enabled/nav_order/navigation_label). Not a generic Page model — see
 * `WebsitePageType`'s own docblock for why page *identity* is a closed,
 * platform-owned enum rather than a database row.
 */
class WebsitePageSetting extends Model
{
    use BelongsToChurch;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'page_type' => WebsitePageType::class,
            'enabled' => 'boolean',
            'nav_order' => 'integer',
        ];
    }
}
