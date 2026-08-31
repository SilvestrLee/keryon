<?php

namespace Tests\Feature\Communications;

use App\Commercial\Entitlements\EntitlementResolver;
use App\Enums\ChurchRole;
use App\Enums\ContentStatus;
use App\Enums\ContentType;
use App\Enums\EntitlementKey;
use App\Enums\WebsiteDraftDestination;
use App\Filament\Pages\WebsiteDraftHandoff;
use App\Models\Church;
use App\Models\ContentItem;
use App\Models\User;
use App\Models\WebsiteHomeContent;
use App\Models\WebsitePublication;
use App\Models\WebsiteSettings;
use App\PublicWebsite\WebsitePublisher;
use App\Support\TenantContext;
use App\Website\Drafts\ApplyApprovedContentToWebsiteDraft;
use App\Website\Drafts\AvailableWebsiteDraftDestinations;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class WebsiteDraftHandoffTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_destination_matrix_and_workflow_ui_are_bounded_and_explicit(): void
    {
        $this->actor();
        $content = $this->approved(ContentType::ANNOUNCEMENT, 'A long Church announcement', '**Join us** this Sunday.');
        $destinations = app(AvailableWebsiteDraftDestinations::class)->for($content->content_type);

        $this->assertEqualsCanonicalizing(
            [WebsiteDraftDestination::HomeHero, WebsiteDraftDestination::HomeWelcome],
            $destinations,
        );

        Livewire::test(WebsiteDraftHandoff::class, ['content' => $content->id])
            ->assertSuccessful()
            ->assertSee('Prepare a Website draft')
            ->assertSee('This updates working Website content and never publishes it.')
            ->set('destination', WebsiteDraftDestination::HomeWelcome->value)
            ->assertSee('Mapping preview')
            ->assertSee('Join us this Sunday.')
            ->assertDontSee('**Join us**');
    }

    public function test_approved_content_is_applied_to_canonical_draft_with_provenance_but_not_published(): void
    {
        [$church, $user] = $this->actor();
        $content = $this->approved(ContentType::ANNOUNCEMENT, 'Sunday at ten', 'Join us this Sunday.');

        $result = app(ApplyApprovedContentToWebsiteDraft::class)->apply($content, WebsiteDraftDestination::HomeHero);

        $home = WebsiteHomeContent::firstOrFail();
        $this->assertSame('Sunday at ten', $home->hero_heading);
        $this->assertSame('Join us this Sunday.', $home->hero_subheading);
        $this->assertFalse($result->alreadyApplied);
        $this->assertSame($church->id, $result->provenance->church_id);
        $this->assertSame($user->id, $result->provenance->applied_by);
        $this->assertSame($content->id, $result->provenance->content_item_id);
        $this->assertDatabaseCount('website_publications', 0);
    }

    public function test_same_approved_version_and_destination_is_idempotent(): void
    {
        $this->actor();
        $content = $this->approved(ContentType::WEBSITE_COPY, 'Welcome', 'You belong here.');
        $action = app(ApplyApprovedContentToWebsiteDraft::class);

        $first = $action->apply($content, WebsiteDraftDestination::HomeWelcome);
        $second = $action->apply($content, WebsiteDraftDestination::HomeWelcome);

        $this->assertTrue($second->alreadyApplied);
        $this->assertSame($first->provenance->id, $second->provenance->id);
        $this->assertDatabaseCount('website_content_provenances', 1);
    }

    public function test_populated_or_diverged_destination_is_never_silently_overwritten(): void
    {
        $this->actor();
        WebsiteHomeContent::create(['welcome_heading' => 'Website editor heading', 'welcome_body' => 'Website editor copy']);
        $content = $this->approved(ContentType::WEBSITE_COPY, 'New heading', 'New copy');

        try {
            app(ApplyApprovedContentToWebsiteDraft::class)->apply($content, WebsiteDraftDestination::HomeWelcome);
            $this->fail('A populated destination was overwritten without confirmation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('destination', $exception->errors());
        }

        app(ApplyApprovedContentToWebsiteDraft::class)->apply($content, WebsiteDraftDestination::HomeWelcome, replace: true);
        WebsiteHomeContent::first()->update(['welcome_body' => 'Adapted by Website editor']);
        $new = $this->approved(ContentType::WEBSITE_COPY, 'Another heading', 'Another body');

        $this->expectException(ValidationException::class);
        app(ApplyApprovedContentToWebsiteDraft::class)->apply($new, WebsiteDraftDestination::HomeWelcome, replace: true);
    }

    public function test_unapproved_and_unsupported_content_are_denied(): void
    {
        $this->actor();
        $draft = ContentItem::create(['title' => 'Draft', 'content_type' => ContentType::WEBSITE_COPY, 'body' => 'Draft body']);

        try {
            app(ApplyApprovedContentToWebsiteDraft::class)->apply($draft, WebsiteDraftDestination::HomeWelcome);
            $this->fail('Draft content was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('source', $exception->errors());
        }

        $social = $this->approved(ContentType::SOCIAL_CAPTION, 'Social', 'Social only');
        $this->expectException(ValidationException::class);
        app(ApplyApprovedContentToWebsiteDraft::class)->apply($social, WebsiteDraftDestination::HomeHero);
    }

    public function test_website_publish_capability_is_not_required_but_website_manage_is(): void
    {
        $church = Church::factory()->create();
        $admin = User::factory()->forChurch($church, [ChurchRole::ADMINISTRATOR])->create();
        $this->actingAs($admin);
        session(['active_church_id' => $church->id]);
        app(TenantContext::class)->forgetResolved();
        $content = ContentItem::create(['title' => 'Source', 'content_type' => ContentType::WEBSITE_COPY, 'body' => 'Body']);
        $content->forceFill(['status' => ContentStatus::APPROVED, 'approved_at' => now()]);
        $content->saveQuietly();

        $this->expectException(AuthorizationException::class);
        app(ApplyApprovedContentToWebsiteDraft::class)->apply($content, WebsiteDraftDestination::HomeWelcome);
    }

    public function test_cross_church_source_cannot_mutate_active_website(): void
    {
        [$church] = $this->actor();
        $other = Church::factory()->create();
        $source = ContentItem::withoutEvents(function () use ($other): ContentItem {
            $item = new ContentItem(['title' => 'Other secret', 'content_type' => ContentType::WEBSITE_COPY, 'body' => 'Private']);
            $item->church_id = $other->id;
            $item->forceFill(['status' => ContentStatus::APPROVED, 'approved_at' => now()]);
            $item->save();

            return $item;
        });

        $this->expectException(AuthorizationException::class);
        app(ApplyApprovedContentToWebsiteDraft::class)->apply($source, WebsiteDraftDestination::HomeWelcome);
        $this->assertSame($church->id, app(TenantContext::class)->currentChurchId());
    }

    public function test_later_manual_publish_remains_the_only_publication_boundary(): void
    {
        $this->actor();
        WebsiteSettings::create([]);
        $content = $this->approved(ContentType::WEBSITE_COPY, 'Working source', 'Working body');
        app(ApplyApprovedContentToWebsiteDraft::class)->apply($content, WebsiteDraftDestination::HomeWelcome);

        $this->assertDatabaseCount('website_publications', 0);
        $publication = app(WebsitePublisher::class)->publish();
        $this->assertInstanceOf(WebsitePublication::class, $publication);
        $this->assertSame('Working source', $publication->snapshot['home']['welcome_heading']);
    }

    public function test_source_fingerprint_changes_only_after_new_approval(): void
    {
        $this->actor();
        $content = $this->approved(ContentType::WEBSITE_COPY, 'First', 'First body');
        $first = app(ApplyApprovedContentToWebsiteDraft::class)->apply($content, WebsiteDraftDestination::HomeWelcome);
        $content->update(['body' => 'Edited body']);
        $this->assertSame(ContentStatus::DRAFT, $content->fresh()->status);

        $this->expectException(ValidationException::class);
        app(ApplyApprovedContentToWebsiteDraft::class)->apply($content->fresh(), WebsiteDraftDestination::HomeWelcome, replace: true);
        $this->assertNotNull($first->provenance->source_fingerprint);
    }

    /** @return array{Church, User} */
    private function actor(): array
    {
        $church = Church::factory()->create();
        $user = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($user);
        session(['active_church_id' => $church->id]);
        app(TenantContext::class)->forgetResolved();
        $resolver = Mockery::mock(EntitlementResolver::class);
        $resolver->shouldReceive('allows')->with(Mockery::type(Church::class), EntitlementKey::WebsiteEnabled)->andReturnTrue();
        $this->app->instance(EntitlementResolver::class, $resolver);

        return [$church, $user];
    }

    private function approved(ContentType $type, string $title, string $body): ContentItem
    {
        $content = ContentItem::create(['title' => $title, 'content_type' => $type, 'body' => $body]);
        $content->forceFill(['status' => ContentStatus::APPROVED, 'approved_by' => auth()->id(), 'approved_at' => now()])->saveQuietly();

        return $content->fresh();
    }
}
