<?php

namespace Tests\Feature\ChurchWebsite;

use App\Enums\WebsitePageShape;
use App\Enums\WebsitePageType;
use Tests\TestCase;

/**
 * K-WEB-V1-001D-B §76 — registry-level tests for the canonical,
 * platform-owned page type. No database, no HTTP — these prove the
 * closed enum itself is internally consistent and fails closed for any
 * unregistered value, independent of routing/theme/publication behavior
 * (covered by the other new test files this milestone adds).
 */
class WebsitePageRegistryTest extends TestCase
{
    public function test_exactly_the_five_current_page_types_are_registered(): void
    {
        $this->assertSame(
            ['home', 'about', 'leadership', 'ministries', 'contact'],
            array_map(fn (WebsitePageType $type): string => $type->value, WebsitePageType::cases()),
        );
    }

    public function test_every_page_type_has_a_unique_key(): void
    {
        $values = array_map(fn (WebsitePageType $type): string => $type->value, WebsitePageType::cases());

        $this->assertSame($values, array_unique($values));
    }

    public function test_every_public_slug_is_unique(): void
    {
        $slugs = array_map(fn (WebsitePageType $type): string => $type->slug(), WebsitePageType::cases());

        $this->assertSame($slugs, array_unique($slugs));
    }

    public function test_required_optional_classification_is_correct(): void
    {
        $this->assertTrue(WebsitePageType::Home->required());
        $this->assertTrue(WebsitePageType::Contact->required());
        $this->assertFalse(WebsitePageType::About->required());
        $this->assertFalse(WebsitePageType::Leadership->required());
        $this->assertFalse(WebsitePageType::Ministries->required());
    }

    public function test_default_labels_resolve_and_are_non_empty(): void
    {
        foreach (WebsitePageType::cases() as $type) {
            $this->assertNotSame('', trim($type->label()));
        }

        $this->assertSame('Home', WebsitePageType::Home->label());
        $this->assertSame('Messages', WebsitePageType::tryFrom('messages')?->label() ?? 'Messages');
    }

    public function test_default_ordering_resolves_and_is_distinct_and_stable(): void
    {
        $orders = array_map(fn (WebsitePageType $type): int => $type->defaultNavOrder(), WebsitePageType::cases());

        $this->assertSame($orders, array_unique($orders));
        $this->assertSame(
            ['home', 'about', 'leadership', 'ministries', 'contact'],
            collect(WebsitePageType::cases())
                ->sortBy(fn (WebsitePageType $type): int => $type->defaultNavOrder())
                ->map(fn (WebsitePageType $type): string => $type->value)
                ->values()
                ->all(),
            'Default ordering must preserve the pre-existing page sequence.',
        );
    }

    public function test_page_shape_resolves_for_every_type(): void
    {
        $this->assertSame(WebsitePageShape::Singleton, WebsitePageType::Home->shape());
        $this->assertSame(WebsitePageShape::Singleton, WebsitePageType::About->shape());
        $this->assertSame(WebsitePageShape::Singleton, WebsitePageType::Contact->shape());
        $this->assertSame(WebsitePageShape::Collection, WebsitePageType::Leadership->shape());
        $this->assertSame(WebsitePageShape::Collection, WebsitePageType::Ministries->shape());
    }

    public function test_navigable_returns_every_current_case(): void
    {
        $this->assertCount(5, WebsitePageType::navigable());
    }

    /**
     * K-WEB-V1-001D-B §57 — a future canonical page-type value must never
     * collide with an already-reserved top-level Website route. This
     * test protects that invariant mechanically, independent of any
     * developer remembering to check it by hand when a future
     * K-WEB-V1-001D-C case is added.
     */
    public function test_canonical_page_slugs_never_collide_with_reserved_website_routes(): void
    {
        $reserved = ['sitemap.xml', 'robots.txt', 'admin', 'app', 'media'];

        foreach (WebsitePageType::cases() as $type) {
            $this->assertNotContains($type->slug(), $reserved, "Page type [{$type->value}] must not use a reserved route slug.");
            $this->assertNotContains($type->value, $reserved, "Page type [{$type->value}] must not use a reserved route slug.");
        }
    }

    /**
     * K-WEB-V1-001D-B §15/§78 — an unregistered page identity must fail
     * closed, never fall through to a generic/default renderer.
     */
    public function test_unknown_page_type_fails_closed(): void
    {
        $this->assertNull(WebsitePageType::tryFrom('events'));
        $this->assertNull(WebsitePageType::tryFrom('messages'));
        $this->assertNull(WebsitePageType::tryFrom('publications'));
        $this->assertNull(WebsitePageType::tryFrom('giving'));
        $this->assertNull(WebsitePageType::tryFrom('anything-arbitrary'));
        $this->assertNull(WebsitePageType::tryFrom(''));
    }
}
