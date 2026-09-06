<?php

namespace App\PublicWebsite;

use App\Enums\WebsitePageType;
use App\Models\WebsitePageSetting;
use Closure;

/**
 * K-WEB-V1-001D-B §35 — the single authoritative resolver for "is this
 * canonical page type enabled, in what order, under what label." Two
 * modes, deliberately kept as two small methods on one class rather than
 * two separate services, since they share the exact same default-
 * resolution rules and only differ in where the override data comes
 * from:
 *
 * - `effectiveForChurch()` — the live, *working* configuration, read
 *   directly from `website_page_settings`. Used by Website Management
 *   (Overview, Page Settings) and by preview.
 * - `effectiveFromSnapshot()` — the immutable, *published* configuration,
 *   read from a `WebsitePublication`'s own stored snapshot. Never
 *   queries the live table — see §49 ("the public Website must not
 *   query live working `website_page_settings`").
 *
 * Both resolve every registered `WebsitePageType`, not just the ones
 * with a stored row — absence of override data is itself a meaningful,
 * legacy-compatible state (§33/§34/§50/§51): a required page type always
 * resolves `enabled = true` regardless of any stored value (defense in
 * depth — the management UI never even offers a way to store `false`
 * for one), and every other page type defaults to `enabled = true` too,
 * preserving the exact pre-K-WEB-V1-001D-B behavior for a Church with no
 * `website_page_settings` rows at all and no `page_settings` key in an
 * old publication snapshot.
 */
class WebsitePageConfiguration
{
    /** @return array<string, array{enabled: bool, nav_order: int, navigation_label: ?string}> */
    public function effectiveForChurch(int $churchId): array
    {
        $rows = WebsitePageSetting::withoutGlobalScope('church_tenant')
            ->where('church_id', $churchId)
            ->get()
            ->keyBy(fn (WebsitePageSetting $setting): string => $setting->page_type->value)
            ->map(fn (WebsitePageSetting $setting): array => [
                'enabled' => $setting->enabled,
                'nav_order' => $setting->nav_order,
                'navigation_label' => $setting->navigation_label,
            ]);

        return $this->resolve(fn (WebsitePageType $type): ?array => $rows->get($type->value));
    }

    /**
     * @param  array<string, array{enabled?: bool, nav_order?: int, navigation_label?: ?string}>|null  $snapshotPageSettings
     * @return array<string, array{enabled: bool, nav_order: int, navigation_label: ?string}>
     */
    public function effectiveFromSnapshot(?array $snapshotPageSettings): array
    {
        $snapshotPageSettings ??= [];

        return $this->resolve(fn (WebsitePageType $type): ?array => $snapshotPageSettings[$type->value] ?? null);
    }

    /** @param Closure(WebsitePageType): (array{enabled?: bool, nav_order?: int, navigation_label?: ?string}|null) $lookup
     * @return array<string, array{enabled: bool, nav_order: int, navigation_label: ?string}> */
    private function resolve(Closure $lookup): array
    {
        $effective = [];

        foreach (WebsitePageType::cases() as $type) {
            $stored = $lookup($type);

            $effective[$type->value] = [
                'enabled' => $type->required() ? true : (bool) ($stored['enabled'] ?? true),
                'nav_order' => $stored['nav_order'] ?? $type->defaultNavOrder(),
                'navigation_label' => $stored['navigation_label'] ?? null,
            ];
        }

        return $effective;
    }
}
