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
 *
 * K-WEB-V1-001D-C §101 — updated to the final nine canonical cases.
 */
class WebsitePageRegistryTest extends TestCase
{
    public function test_exactly_the_nine_canonical_page_types_are_registered(): void
    {
        $this->assertSame(
            ['home', 'about', 'leadership', 'ministries', 'events', 'messages', 'publications', 'giving', 'contact'],
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

        foreach ([
            WebsitePageType::About, WebsitePageType::Leadership, WebsitePageType::Ministries,
            WebsitePageType::Events, WebsitePageType::Messages, WebsitePageType::Publications, WebsitePageType::Giving,
        ] as $optional) {
            $this->assertFalse($optional->required(), "{$optional->value} must be optional.");
        }
    }

    public function test_default_labels_resolve_and_are_non_empty(): void
    {
        foreach (WebsitePageType::cases() as $type) {
            $this->assertNotSame('', trim($type->label()));
        }

        $this->assertSame('Home', WebsitePageType::Home->label());
        $this->assertSame('Messages', WebsitePageType::Messages->label());
        $this->assertSame('Publications', WebsitePageType::Publications->label());
        $this->assertSame('Giving', WebsitePageType::Giving->label());
        $this->assertSame('Events', WebsitePageType::Events->label());
    }

    public function test_default_ordering_resolves_and_is_distinct_and_stable(): void
    {
        $orders = array_map(fn (WebsitePageType $type): int => $type->defaultNavOrder(), WebsitePageType::cases());

        $this->assertSame($orders, array_unique($orders));
        $this->assertSame(
            ['home', 'about', 'leadership', 'ministries', 'events', 'messages', 'publications', 'giving', 'contact'],
            collect(WebsitePageType::cases())
                ->sortBy(fn (WebsitePageType $type): int => $type->defaultNavOrder())
                ->map(fn (WebsitePageType $type): string => $type->value)
                ->values()
                ->all(),
            'K-WEB-V1-001D-C §11 — the four new types must insert between Ministries and Contact without disturbing the original five-page sequence.',
        );
    }

    public function test_page_shape_resolves_for_every_type(): void
    {
        $this->assertSame(WebsitePageShape::Singleton, WebsitePageType::Home->shape());
        $this->assertSame(WebsitePageShape::Singleton, WebsitePageType::About->shape());
        $this->assertSame(WebsitePageShape::Singleton, WebsitePageType::Contact->shape());
        $this->assertSame(WebsitePageShape::Singleton, WebsitePageType::Giving->shape());
        $this->assertSame(WebsitePageShape::Collection, WebsitePageType::Leadership->shape());
        $this->assertSame(WebsitePageShape::Collection, WebsitePageType::Ministries->shape());
        $this->assertSame(WebsitePageShape::Collection, WebsitePageType::Events->shape());
        $this->assertSame(WebsitePageShape::Collection, WebsitePageType::Messages->shape());
        $this->assertSame(WebsitePageShape::Collection, WebsitePageType::Publications->shape());
    }

    public function test_navigable_returns_every_current_case(): void
    {
        $this->assertCount(9, WebsitePageType::navigable());
    }

    /**
     * K-WEB-V1-001D-B §57 — a future canonical page-type value must never
     * collide with an already-reserved top-level Website route. This
     * test protects that invariant mechanically, independent of any
     * developer remembering to check it by hand when a future case is
     * added.
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
        $this->assertNull(WebsitePageType::tryFrom('sermons'));
        $this->assertNull(WebsitePageType::tryFrom('books'));
        $this->assertNull(WebsitePageType::tryFrom('donations'));
        $this->assertNull(WebsitePageType::tryFrom('anything-arbitrary'));
        $this->assertNull(WebsitePageType::tryFrom(''));
    }

    /**
     * K-WEB-V1-001D-C §7 — the four types this milestone adds, confirmed
     * genuinely registered (the mirror image of the fail-closed proof
     * above).
     */
    public function test_the_four_new_page_types_are_now_registered(): void
    {
        $this->assertSame(WebsitePageType::Events, WebsitePageType::tryFrom('events'));
        $this->assertSame(WebsitePageType::Messages, WebsitePageType::tryFrom('messages'));
        $this->assertSame(WebsitePageType::Publications, WebsitePageType::tryFrom('publications'));
        $this->assertSame(WebsitePageType::Giving, WebsitePageType::tryFrom('giving'));
    }
}
