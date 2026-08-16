<?php

namespace Tests\Feature\Tenancy;

use App\Models\Church;
use App\Models\ContentItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrossChurchContentItemAccessTest extends TestCase
{
    use RefreshDatabase;

    protected Church $churchA;

    protected Church $churchB;

    protected User $userA;

    protected User $userB;

    protected ContentItem $contentItemA;

    protected ContentItem $contentItemB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->churchA = Church::create([
            'name' => 'Church A',
            'slug' => 'church-a',
        ]);

        $this->churchB = Church::create([
            'name' => 'Church B',
            'slug' => 'church-b',
        ]);

        $this->userA = User::factory()->create(['church_id' => $this->churchA->id]);
        $this->userB = User::factory()->create(['church_id' => $this->churchB->id]);

        $this->actingAs($this->userA);
        $this->contentItemA = ContentItem::create([
            'title' => 'Church A Announcement',
            'content_type' => 'general',
            'body' => 'Content for Church A.',
        ]);

        $this->actingAs($this->userB);
        $this->contentItemB = ContentItem::create([
            'title' => 'Church B Announcement',
            'content_type' => 'general',
            'body' => 'Content for Church B.',
        ]);
    }

    public function test_church_a_user_sees_only_church_a_content(): void
    {
        $this->actingAs($this->userA);

        $ids = ContentItem::query()->pluck('id')->all();

        $this->assertContains($this->contentItemA->id, $ids);
        $this->assertNotContains($this->contentItemB->id, $ids);
    }

    public function test_church_b_user_sees_only_church_b_content(): void
    {
        $this->actingAs($this->userB);

        $ids = ContentItem::query()->pluck('id')->all();

        $this->assertContains($this->contentItemB->id, $ids);
        $this->assertNotContains($this->contentItemA->id, $ids);
    }

    public function test_church_a_user_cannot_resolve_church_b_content(): void
    {
        $this->actingAs($this->userA);

        $resolved = ContentItem::query()->find($this->contentItemB->id);

        $this->assertNull($resolved);
    }

    public function test_church_a_user_cannot_update_church_b_content(): void
    {
        $this->actingAs($this->userA);

        $this->assertFalse($this->userA->can('update', $this->contentItemB));
    }

    public function test_church_a_user_cannot_approve_church_b_content(): void
    {
        $this->actingAs($this->userA);

        $this->assertFalse($this->userA->can('approve', $this->contentItemB));
    }

    public function test_content_item_auto_assigns_church_id_from_authenticated_user(): void
    {
        $this->actingAs($this->userA);

        $contentItem = ContentItem::create([
            'title' => 'No explicit church_id',
            'content_type' => 'general',
            'body' => 'Body text.',
        ]);

        $this->assertSame($this->churchA->id, $contentItem->church_id);
    }

    public function test_no_authenticated_church_context_fails_closed(): void
    {
        // setUp() leaves userB authenticated (its last action); explicitly
        // clear that so this test genuinely exercises the unauthenticated
        // path rather than accidentally inheriting userB's session.
        $this->app['auth']->forgetGuards();

        $ids = ContentItem::query()->pluck('id')->all();

        $this->assertEmpty($ids);
    }
}
