<?php

namespace Tests\Feature\Authorization;

use App\Enums\ChurchRole;
use App\Models\Church;
use App\Models\ContentItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves the Content Studio responsibility boundary (Keryon Blueprint
 * v1.4.1 §9/§11, K-AUTH-001B §30-§31/§48): Content Studio requires
 * Communications; Administrator does not inherit it; no separate
 * content.approve capability exists — lifecycle authorization and
 * workflow validity remain separate concerns.
 */
class ContentStudioAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function memberOf(Church $church, array $roles = [], bool $primary = false): User
    {
        return User::factory()->forChurch($church, $roles, $primary)->create();
    }

    protected function seedContentItem(User $author): ContentItem
    {
        $this->actingAs($author);

        return ContentItem::create(['title' => 'Draft', 'content_type' => 'general', 'body' => 'Body.']);
    }

    public function test_communications_can_perform_the_full_lifecycle(): void
    {
        $church = Church::create(['name' => 'Comms Church', 'slug' => 'content-comms-church']);
        $author = $this->memberOf($church, [ChurchRole::COMMUNICATIONS]);
        $item = $this->seedContentItem($author);

        $this->assertTrue($author->can('viewAny', ContentItem::class));
        $this->assertTrue($author->can('create', ContentItem::class));
        $this->assertTrue($author->can('view', $item));
        $this->assertTrue($author->can('update', $item));
        $this->assertTrue($author->can('delete', $item));
        $this->assertTrue($author->can('submitForReview', $item));
        $this->assertTrue($author->can('approve', $item));
        $this->assertTrue($author->can('requestChanges', $item));
    }

    public function test_administrator_only_cannot_use_content_studio(): void
    {
        $church = Church::create(['name' => 'Admin Church', 'slug' => 'content-admin-church']);
        $comms = $this->memberOf($church, [ChurchRole::COMMUNICATIONS]);
        $item = $this->seedContentItem($comms);

        $admin = $this->memberOf($church, [ChurchRole::ADMINISTRATOR]);
        $this->actingAs($admin);

        $this->assertFalse($admin->can('viewAny', ContentItem::class));
        $this->assertFalse($admin->can('create', ContentItem::class));
        $this->assertFalse($admin->can('view', $item));
        $this->assertFalse($admin->can('update', $item));
    }

    public function test_care_only_cannot_use_content_studio(): void
    {
        $church = Church::create(['name' => 'Care Church', 'slug' => 'content-care-church']);
        $comms = $this->memberOf($church, [ChurchRole::COMMUNICATIONS]);
        $item = $this->seedContentItem($comms);

        $care = $this->memberOf($church, [ChurchRole::CARE]);
        $this->actingAs($care);

        $this->assertFalse($care->can('viewAny', ContentItem::class));
        $this->assertFalse($care->can('create', ContentItem::class));
        $this->assertFalse($care->can('view', $item));
    }

    public function test_primary_only_cannot_use_content_studio(): void
    {
        $church = Church::create(['name' => 'Primary Church', 'slug' => 'content-primary-church']);
        $user = $this->memberOf($church, [], primary: true);

        $this->actingAs($user);

        $this->assertFalse($user->can('viewAny', ContentItem::class));
        $this->assertFalse($user->can('create', ContentItem::class));
    }

    public function test_administrator_plus_communications_can_use_content_studio(): void
    {
        $church = Church::create(['name' => 'Combo Church', 'slug' => 'content-combo-church']);
        $user = $this->memberOf($church, [ChurchRole::ADMINISTRATOR, ChurchRole::COMMUNICATIONS]);

        $this->actingAs($user);

        $this->assertTrue($user->can('viewAny', ContentItem::class));
        $this->assertTrue($user->can('create', ContentItem::class));
    }

    public function test_communications_plus_care_can_use_content_studio(): void
    {
        $church = Church::create(['name' => 'Combo Care Church', 'slug' => 'content-combo-care-church']);
        $user = $this->memberOf($church, [ChurchRole::COMMUNICATIONS, ChurchRole::CARE]);

        $this->actingAs($user);

        $this->assertTrue($user->can('viewAny', ContentItem::class));
        $this->assertTrue($user->can('create', ContentItem::class));
    }

    public function test_administrator_plus_care_still_denied_content_studio(): void
    {
        $church = Church::create(['name' => 'Admin Care Church', 'slug' => 'content-admin-care-church']);
        $user = $this->memberOf($church, [ChurchRole::ADMINISTRATOR, ChurchRole::CARE]);

        $this->actingAs($user);

        $this->assertFalse($user->can('viewAny', ContentItem::class));
        $this->assertFalse($user->can('create', ContentItem::class));
    }

    public function test_cross_church_content_access_is_denied_even_with_communications(): void
    {
        $churchA = Church::create(['name' => 'Church A', 'slug' => 'content-church-a']);
        $churchB = Church::create(['name' => 'Church B', 'slug' => 'content-church-b']);

        $commsB = $this->memberOf($churchB, [ChurchRole::COMMUNICATIONS]);
        $itemB = $this->seedContentItem($commsB);

        $commsA = $this->memberOf($churchA, [ChurchRole::COMMUNICATIONS]);
        $this->actingAs($commsA);

        $this->assertFalse($commsA->can('view', $itemB));
        $this->assertFalse($commsA->can('update', $itemB));
        $this->assertFalse($commsA->can('approve', $itemB));
    }

    public function test_no_tenant_context_denies_content_studio_access(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->assertFalse($user->can('viewAny', ContentItem::class));
    }

    /**
     * Lifecycle validity (draft/review/approved state machine) and
     * authorization (does this membership hold content.manage) are
     * separate concerns — a Communications membership is authorized for
     * every lifecycle action regardless of the record's current state; the
     * *domain* methods on ContentItem are what enforce valid transitions
     * (see ContentItem::approve() etc., which throw LogicException on an
     * invalid transition, independent of the Policy layer tested here).
     */
    public function test_authorization_is_independent_of_lifecycle_state(): void
    {
        $church = Church::create(['name' => 'Lifecycle Church', 'slug' => 'content-lifecycle-church']);
        $comms = $this->memberOf($church, [ChurchRole::COMMUNICATIONS]);
        $item = $this->seedContentItem($comms);

        // Still a plain draft — approve() would be a domain-logic error,
        // not an authorization error. The Policy allows the attempt.
        $this->assertTrue($comms->can('approve', $item));
    }
}
