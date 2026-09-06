<?php

namespace App\Enums;

/**
 * K-WEB-V1-001D-B §14 — every curated Website page type observed in this
 * codebase is unambiguously one of these two content shapes (confirmed
 * in K-WEB-V1-001D-A discovery §5/§6): a `Singleton` (one row per Church,
 * managed via the existing `ManagesSingletonRecord` trait — Home, About,
 * Contact) or a `Collection` (many `sort_order`-ordered rows, managed via
 * a Filament Resource — Leadership, Ministries). This is descriptive
 * metadata only; it does not drive any dynamic/reflection-based
 * behavior (see `WebsitePageType`'s own docblock and the K-WEB-V1-001D-B
 * directive §115's warning against premature generic abstraction).
 */
enum WebsitePageShape: string
{
    case Singleton = 'singleton';
    case Collection = 'collection';
}
