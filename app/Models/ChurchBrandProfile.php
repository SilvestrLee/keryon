<?php

namespace App\Models;

use App\Enums\BrandFontChoice;
use App\Models\Concerns\BelongsToChurch;
use App\Models\Concerns\ValidatesMediaAssetOwnership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * K-CHURCHWEB-001B §7-§11 — the shared Church Brand Profile. One per
 * Church (`church_id` unique — enforced at the database level via the
 * migration). Not Website-owned: consumed by Website now, and by Design
 * Studio/Campaigns later without duplication (report §31).
 *
 * Deliberately excludes a "secondary logo" field from earlier brand
 * brainstorming — see the K-CHURCHWEB-001B report §4 for why only
 * `primary_logo` and `mark` (a genuinely distinct square icon/symbol use
 * case) are included. Deliberately excludes institutional identity
 * fields (name/email/phone/address/social handles) that already have a
 * canonical home on `Church`/`ChurchSocialLink` — see §22.
 */
class ChurchBrandProfile extends Model
{
    use BelongsToChurch;
    use ValidatesMediaAssetOwnership;

    protected $fillable = [
        'primary_logo_media_id',
        'mark_media_id',
        'primary_color',
        'secondary_color',
        'accent_color',
        'heading_font',
        'body_font',
    ];

    protected function casts(): array
    {
        return [
            'heading_font' => BrandFontChoice::class,
            'body_font' => BrandFontChoice::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $profile) {
            foreach (['primary_color', 'secondary_color', 'accent_color'] as $field) {
                $profile->{$field} = self::validatedHexColor($profile->{$field});
            }
        });
    }

    /**
     * K-CHURCHWEB-001B §10 — Color Safety. Domain-level validation only:
     * reject obviously malformed values before they enter canonical Brand
     * Profile data. This is not a contrast/accessibility engine — that
     * remains a later theme-rendering responsibility (§26 of the
     * K-CHURCHWEB-001B report).
     */
    public static function validatedHexColor(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
            throw new InvalidArgumentException(
                "Invalid brand color [{$value}] — expected a 6-digit hex color (e.g. #132E35)."
            );
        }

        return strtoupper($value);
    }

    public function primaryLogo(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'primary_logo_media_id');
    }

    public function mark(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'mark_media_id');
    }

    public function mediaAssetForeignKeys(): array
    {
        return ['primary_logo_media_id', 'mark_media_id'];
    }
}
