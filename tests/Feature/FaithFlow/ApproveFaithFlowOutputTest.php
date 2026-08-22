<?php

namespace Tests\Feature\FaithFlow;

use App\Enums\ChurchRole;
use App\Enums\ContentOrigin;
use App\Enums\FaithFlowOutputStatus;
use App\Enums\FaithFlowOutputType;
use App\Enums\FaithFlowRunStatus;
use App\FaithFlow\Actions\ApproveFaithFlowOutput;
use App\Models\Church;
use App\Models\ContentItem;
use App\Models\FaithFlowOutput;
use App\Models\FaithFlowRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/**
 * K-FAITHFLOW-001E §9/§10/§20/§21/§53 — the human-approval action, and its
 * conditional, atomic Content Studio handoff for mapped output types.
 */
class ApproveFaithFlowOutputTest extends TestCase
{
    use RefreshDatabase;

    protected Church $church;

    protected User $communications;

    protected function setUp(): void
    {
        parent::setUp();

        $this->church = Church::create(['name' => 'Approval Test Church', 'slug' => 'approval-test-church-'.uniqid()]);
        $this->communications = User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($this->communications);
    }

    protected function analyzedRun(array $canonicalAnalysisOverrides = []): FaithFlowRun
    {
        return FaithFlowRun::factory()->forChurch($this->church)->create([
            'status' => FaithFlowRunStatus::ANALYZED,
            'canonical_analysis' => array_merge([
                'source_summary' => 'A sermon about hope in difficult seasons.',
                'principal_message' => 'God remains faithful even in hardship.',
                'key_themes' => ['hope'],
                'notable_statements' => [],
                'scripture_references' => [],
                'ministry_context' => 'Sunday sermon',
                'audience_clues' => null,
                'tone' => 'Encouraging',
            ], $canonicalAnalysisOverrides),
        ]);
    }

    protected function generatedOutput(FaithFlowOutputType $type = FaithFlowOutputType::DEVOTIONAL, ?FaithFlowRun $run = null): FaithFlowOutput
    {
        return FaithFlowOutput::factory()->forRun($run ?? $this->analyzedRun())->create([
            'output_type' => $type,
            'status' => FaithFlowOutputStatus::GENERATED,
            'generated_content' => 'A generated devotional.',
            'content' => 'A generated devotional.',
        ]);
    }

    // --- §53 core approval mechanics ---

    public function test_generated_output_can_be_approved(): void
    {
        $output = $this->generatedOutput();

        $result = app(ApproveFaithFlowOutput::class)->handle($output);

        $this->assertSame(FaithFlowOutputStatus::APPROVED, $result->status);
    }

    public function test_unedited_generated_output_can_be_approved(): void
    {
        $output = $this->generatedOutput();
        $this->assertFalse($output->isEdited());

        $result = app(ApproveFaithFlowOutput::class)->handle($output);

        $this->assertSame(FaithFlowOutputStatus::APPROVED, $result->status);
        $this->assertSame('A generated devotional.', $result->content);
    }

    public function test_human_edited_generated_output_can_be_approved(): void
    {
        $output = $this->generatedOutput();
        $output->forceFill(['content' => 'A human-edited devotional.', 'edited_at' => now()])->save();

        $result = app(ApproveFaithFlowOutput::class)->handle($output->fresh());

        $this->assertSame(FaithFlowOutputStatus::APPROVED, $result->status);
        $this->assertSame('A human-edited devotional.', $result->content);
    }

    public function test_approval_records_the_actor(): void
    {
        $output = $this->generatedOutput();

        $result = app(ApproveFaithFlowOutput::class)->handle($output);

        $this->assertSame($this->communications->id, $result->approved_by);
    }

    // --- K-FAITHFLOW-001E-R1: approval attribution trust boundary ---

    /**
     * §5 "Correct actor" — the authenticated Communications user approving
     * their own-Church output is stamped as the approver, with no
     * parameter to pass at all: attribution is resolved entirely from
     * TenantContext's own trusted seam.
     */
    public function test_approver_is_resolved_from_the_authenticated_tenant_context(): void
    {
        $output = $this->generatedOutput();

        $result = app(ApproveFaithFlowOutput::class)->handle($output);

        $this->assertSame($this->communications->id, $result->approved_by);
    }

    /**
     * §5 "Arbitrary other User" — ApproveFaithFlowOutput::handle() no
     * longer accepts a User parameter at all (K-FAITHFLOW-001E-R1 §3
     * option 1), so there is no argument through which a caller could ever
     * supply an unrelated User as approver. This proves the structural
     * guarantee directly: a second Communications user (User B) exists in
     * the same Church, but approving while authenticated as User A can
     * never attribute the approval to User B — there is nothing in
     * handle()'s signature that could cause that, and approved_by is
     * always exactly the authenticated actor.
     */
    public function test_approval_can_never_be_attributed_to_a_different_authenticated_user(): void
    {
        $userB = User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create();
        $output = $this->generatedOutput();

        // Still acting as $this->communications (User A) — set up in setUp().
        $result = app(ApproveFaithFlowOutput::class)->handle($output);

        $this->assertSame($this->communications->id, $result->approved_by);
        $this->assertNotSame($userB->id, $result->approved_by);
    }

    /**
     * §5 "Cross-Church identity" — a User whose only membership is at a
     * different Church can never become the trusted actor for this
     * Church's output, because TenantContext::currentMembership() simply
     * cannot resolve a membership at this Church for them at all.
     */
    public function test_cross_church_user_cannot_be_the_trusted_approval_actor(): void
    {
        $otherChurch = Church::create(['name' => 'Attribution Other Church', 'slug' => 'attribution-other-church-'.uniqid()]);
        $otherChurchUser = User::factory()->forChurch($otherChurch, [ChurchRole::COMMUNICATIONS])->create();
        $output = $this->generatedOutput();

        $this->actingAs($otherChurchUser);

        try {
            app(ApproveFaithFlowOutput::class)->handle($output);
            $this->fail('Expected a LogicException — the active membership belongs to a different Church.');
        } catch (LogicException) {
            // expected
        }

        $fresh = $output->fresh();
        $this->assertSame(FaithFlowOutputStatus::GENERATED, $fresh->status);
        $this->assertNull($fresh->approved_by);
    }

    /**
     * No authenticated actor / no active TenantContext at all: fails
     * closed, exactly like every other tenant-scoped operation in Keryon.
     */
    public function test_no_active_tenant_context_cannot_approve(): void
    {
        $output = $this->generatedOutput();
        $unaffiliatedUser = User::factory()->create();

        $this->actingAs($unaffiliatedUser);

        $this->expectException(LogicException::class);

        app(ApproveFaithFlowOutput::class)->handle($output);
    }

    public function test_approval_records_a_server_controlled_timestamp(): void
    {
        $output = $this->generatedOutput();
        $this->assertNull($output->approved_at);

        $result = app(ApproveFaithFlowOutput::class)->handle($output);

        $this->assertNotNull($result->approved_at);
    }

    public function test_pending_output_cannot_be_approved(): void
    {
        $output = FaithFlowOutput::factory()->forRun($this->analyzedRun())->create(['output_type' => FaithFlowOutputType::DEVOTIONAL]);

        $this->expectException(LogicException::class);

        app(ApproveFaithFlowOutput::class)->handle($output);
    }

    public function test_generating_output_cannot_be_approved(): void
    {
        $output = $this->generatedOutput();
        $output->forceFill(['status' => FaithFlowOutputStatus::GENERATING])->save();

        $this->expectException(LogicException::class);

        app(ApproveFaithFlowOutput::class)->handle($output->fresh());
    }

    public function test_failed_output_cannot_be_approved(): void
    {
        $output = FaithFlowOutput::factory()->forRun($this->analyzedRun())->failed()->create(['output_type' => FaithFlowOutputType::DEVOTIONAL]);

        $this->expectException(LogicException::class);

        app(ApproveFaithFlowOutput::class)->handle($output);
    }

    public function test_already_approved_invocation_is_idempotent(): void
    {
        $output = $this->generatedOutput();
        $first = app(ApproveFaithFlowOutput::class)->handle($output);

        $second = app(ApproveFaithFlowOutput::class)->handle($first->fresh());

        $this->assertSame($first->approved_at->toDateTimeString(), $second->approved_at->toDateTimeString());
        $this->assertSame($first->content_item_id, $second->content_item_id);
        $this->assertSame(1, ContentItem::query()->count());
    }

    // --- §11/§16/§39: handoff happens as part of approval for mapped types, exactly once ---

    public function test_approving_a_content_studio_mapped_output_creates_exactly_one_content_item(): void
    {
        $output = $this->generatedOutput(FaithFlowOutputType::DEVOTIONAL);

        $result = app(ApproveFaithFlowOutput::class)->handle($output);

        $this->assertNotNull($result->content_item_id);
        $this->assertSame(1, ContentItem::query()->count());
    }

    public function test_repeated_approval_never_duplicates_the_content_item(): void
    {
        $output = $this->generatedOutput(FaithFlowOutputType::DEVOTIONAL);

        $first = app(ApproveFaithFlowOutput::class)->handle($output);
        app(ApproveFaithFlowOutput::class)->handle($first->fresh());
        app(ApproveFaithFlowOutput::class)->handle($first->fresh());

        $this->assertSame(1, ContentItem::query()->count());
    }

    // --- §12/§16/§25: Key Themes / Key Quotes stay FaithFlow-native ---

    public function test_approving_key_themes_does_not_create_a_content_item(): void
    {
        $output = $this->generatedOutput(FaithFlowOutputType::KEY_THEMES);

        $result = app(ApproveFaithFlowOutput::class)->handle($output);

        $this->assertSame(FaithFlowOutputStatus::APPROVED, $result->status);
        $this->assertNull($result->content_item_id);
        $this->assertSame(0, ContentItem::query()->count());
    }

    public function test_approving_key_quotes_does_not_create_a_content_item(): void
    {
        $output = $this->generatedOutput(FaithFlowOutputType::KEY_QUOTES);

        $result = app(ApproveFaithFlowOutput::class)->handle($output);

        $this->assertSame(FaithFlowOutputStatus::APPROVED, $result->status);
        $this->assertNull($result->content_item_id);
        $this->assertSame(0, ContentItem::query()->count());
    }

    // --- §20/§21/§59: transactional integrity — a handoff failure must not half-approve ---

    public function test_a_handoff_failure_rolls_back_the_approval_entirely(): void
    {
        $output = $this->generatedOutput(FaithFlowOutputType::DEVOTIONAL);
        // Force the handoff guard (CreateContentItemFromFaithFlow's
        // non-empty-body check) to fail mid-transaction by leaving no
        // usable content — a realistic data-integrity edge case, not
        // framework-internals mocking.
        $output->forceFill(['content' => '   '])->save();

        try {
            app(ApproveFaithFlowOutput::class)->handle($output->fresh());
            $this->fail('Expected a LogicException from the handoff guard.');
        } catch (LogicException) {
            // expected
        }

        $fresh = $output->fresh();
        $this->assertSame(FaithFlowOutputStatus::GENERATED, $fresh->status);
        $this->assertNull($fresh->approved_at);
        $this->assertNull($fresh->approved_by);
        $this->assertNull($fresh->content_item_id);
        $this->assertSame(0, ContentItem::query()->count());
    }

    // --- §14/§18/§19/§20/§30/§31: ContentItem shape ---

    public function test_handed_off_content_item_uses_correct_content_type_origin_and_church(): void
    {
        $output = $this->generatedOutput(FaithFlowOutputType::DEVOTIONAL);

        $result = app(ApproveFaithFlowOutput::class)->handle($output);
        $contentItem = $result->contentItem;

        $this->assertSame(\App\Enums\ContentType::DEVOTIONAL, $contentItem->content_type);
        $this->assertSame(ContentOrigin::FAITHFLOW, $contentItem->origin);
        $this->assertSame(\App\Enums\ContentStatus::DRAFT, $contentItem->status);
        $this->assertSame($this->church->id, $contentItem->church_id);
    }

    public function test_handed_off_content_item_uses_the_human_working_copy_not_the_raw_generated_text(): void
    {
        $output = $this->generatedOutput(FaithFlowOutputType::DEVOTIONAL);
        $output->forceFill(['content' => 'The human-edited final text.', 'edited_at' => now()])->save();

        $result = app(ApproveFaithFlowOutput::class)->handle($output->fresh());

        $this->assertSame('The human-edited final text.', $result->contentItem->body);
        $this->assertSame('A generated devotional.', $result->generated_content);
    }

    public function test_handed_off_content_item_has_a_meaningful_deterministic_title(): void
    {
        $output = $this->generatedOutput(FaithFlowOutputType::DEVOTIONAL);

        $result = app(ApproveFaithFlowOutput::class)->handle($output);

        $this->assertSame('Sunday Sermon — Devotional', $result->contentItem->title);
    }

    public function test_no_faithflow_usage_row_is_created_for_approval(): void
    {
        $output = $this->generatedOutput(FaithFlowOutputType::DEVOTIONAL);

        app(ApproveFaithFlowOutput::class)->handle($output);

        $this->assertSame(0, \App\Models\FaithFlowUsage::query()->count());
    }

    // --- §40/§60: soft-deleted handoff target is never silently replaced ---

    public function test_soft_deleted_handoff_content_item_is_not_silently_recreated_on_reapproval(): void
    {
        $output = $this->generatedOutput(FaithFlowOutputType::DEVOTIONAL);
        $approved = app(ApproveFaithFlowOutput::class)->handle($output);
        $originalContentItemId = $approved->content_item_id;

        ContentItem::find($originalContentItemId)->delete();

        $reapproved = app(ApproveFaithFlowOutput::class)->handle($approved->fresh());

        $this->assertSame($originalContentItemId, $reapproved->content_item_id);
        $this->assertSame(0, ContentItem::query()->count());
        $this->assertSame(1, ContentItem::withTrashed()->count());
    }
}
