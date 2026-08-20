<?php

namespace Tests\Feature\FaithFlow;

use App\Enums\ChurchRole;
use App\Enums\ContentOrigin;
use App\Enums\ContentType;
use App\Enums\FaithFlowOutputStatus;
use App\Enums\FaithFlowOutputType;
use App\Enums\FaithFlowRunStatus;
use App\FaithFlow\Actions\CreateContentItemFromFaithFlow;
use App\Models\Church;
use App\Models\ContentItem;
use App\Models\FaithFlowOutput;
use App\Models\FaithFlowRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/**
 * K-FAITHFLOW-001E §13/§17/§18/§30-§32/§54 — the explicit ContentItem-
 * creation seam, exercised directly (independent of ApproveFaithFlowOutput,
 * which is the normal caller).
 */
class CreateContentItemFromFaithFlowTest extends TestCase
{
    use RefreshDatabase;

    protected Church $church;

    protected function setUp(): void
    {
        parent::setUp();

        $this->church = Church::create(['name' => 'Handoff Test Church', 'slug' => 'handoff-test-church-'.uniqid()]);
        $this->actingAs(User::factory()->forChurch($this->church, [ChurchRole::COMMUNICATIONS])->create());
    }

    protected function analyzedRun(array $canonicalAnalysisOverrides = []): FaithFlowRun
    {
        return FaithFlowRun::factory()->forChurch($this->church)->create([
            'status' => FaithFlowRunStatus::ANALYZED,
            'canonical_analysis' => array_merge([
                'source_summary' => 'Walking in faith through every season of life.',
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

    protected function generatedOutput(FaithFlowOutputType $type, ?FaithFlowRun $run = null): FaithFlowOutput
    {
        return FaithFlowOutput::factory()->forRun($run ?? $this->analyzedRun())->create([
            'output_type' => $type,
            'status' => FaithFlowOutputStatus::GENERATED,
            'generated_content' => 'Generated content.',
            'content' => 'Final human content.',
        ]);
    }

    // --- §54: every Content-Studio-capable output type, individually (no
    // data provider — matches this suite's established one-test-per-case
    // convention, e.g. GenerateFaithFlowOutputTest's "one deterministic
    // success test per output type") ---

    protected function assertHandsOffCorrectly(FaithFlowOutputType $type, ContentType $expectedContentType): void
    {
        $output = $this->generatedOutput($type);

        $contentItem = app(CreateContentItemFromFaithFlow::class)->handle($output);

        $this->assertSame($expectedContentType, $contentItem->content_type);
        $this->assertSame(ContentOrigin::FAITHFLOW, $contentItem->origin);
        $this->assertSame($this->church->id, $contentItem->church_id);
        $this->assertSame('Final human content.', $contentItem->body);
        $this->assertSame($contentItem->id, $output->fresh()->content_item_id);
    }

    public function test_sermon_summary_hands_off_to_the_correct_content_type(): void
    {
        $this->assertHandsOffCorrectly(FaithFlowOutputType::SERMON_SUMMARY, ContentType::SERMON_SUMMARY);
    }

    public function test_devotional_hands_off_to_the_correct_content_type(): void
    {
        $this->assertHandsOffCorrectly(FaithFlowOutputType::DEVOTIONAL, ContentType::DEVOTIONAL);
    }

    public function test_prayer_points_hands_off_to_the_correct_content_type(): void
    {
        $this->assertHandsOffCorrectly(FaithFlowOutputType::PRAYER_POINTS, ContentType::PRAYER_POINTS);
    }

    public function test_social_captions_hands_off_to_the_correct_content_type(): void
    {
        $this->assertHandsOffCorrectly(FaithFlowOutputType::SOCIAL_CAPTIONS, ContentType::SOCIAL_CAPTION);
    }

    public function test_whatsapp_status_copy_hands_off_to_the_correct_content_type(): void
    {
        $this->assertHandsOffCorrectly(FaithFlowOutputType::WHATSAPP_STATUS_COPY, ContentType::WHATSAPP_STATUS_COPY);
    }

    public function test_discussion_questions_hands_off_to_the_correct_content_type(): void
    {
        $this->assertHandsOffCorrectly(FaithFlowOutputType::DISCUSSION_QUESTIONS, ContentType::DISCUSSION_QUESTIONS);
    }

    // --- §12/§16/§25: reference outputs are never handed off ---

    public function test_key_themes_cannot_be_handed_off(): void
    {
        $output = $this->generatedOutput(FaithFlowOutputType::KEY_THEMES);

        $this->expectException(LogicException::class);

        app(CreateContentItemFromFaithFlow::class)->handle($output);
    }

    public function test_key_quotes_cannot_be_handed_off(): void
    {
        $output = $this->generatedOutput(FaithFlowOutputType::KEY_QUOTES);

        $this->expectException(LogicException::class);

        app(CreateContentItemFromFaithFlow::class)->handle($output);
    }

    // --- §16/§39: idempotency, invoked directly (defense in depth beyond ApproveFaithFlowOutput's own short circuit) ---

    public function test_calling_handoff_twice_directly_does_not_duplicate(): void
    {
        $output = $this->generatedOutput(FaithFlowOutputType::DEVOTIONAL);

        $first = app(CreateContentItemFromFaithFlow::class)->handle($output);
        $second = app(CreateContentItemFromFaithFlow::class)->handle($output->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, ContentItem::query()->count());
    }

    // --- §18/§19/§32: title strategy ---

    public function test_title_uses_ministry_context_when_present(): void
    {
        $output = $this->generatedOutput(FaithFlowOutputType::DEVOTIONAL, $this->analyzedRun(['ministry_context' => 'Sunday sermon']));

        $contentItem = app(CreateContentItemFromFaithFlow::class)->handle($output);

        $this->assertSame('Sunday Sermon — Devotional', $contentItem->title);
    }

    public function test_title_falls_back_to_source_summary_when_ministry_context_is_blank(): void
    {
        $run = $this->analyzedRun([
            'ministry_context' => null,
            'source_summary' => 'A message about walking faithfully through every season of ordinary life together.',
        ]);
        $output = $this->generatedOutput(FaithFlowOutputType::PRAYER_POINTS, $run);

        $contentItem = app(CreateContentItemFromFaithFlow::class)->handle($output);

        // First 8 words of source_summary, Title-Cased — proving both the
        // fallback and the truncation.
        $this->assertSame('A Message About Walking Faithfully Through Every Season — Prayer Points', $contentItem->title);
    }

    public function test_title_never_exposes_internal_identifiers(): void
    {
        $output = $this->generatedOutput(FaithFlowOutputType::DEVOTIONAL);

        $contentItem = app(CreateContentItemFromFaithFlow::class)->handle($output);

        $this->assertStringNotContainsString((string) $output->id, $contentItem->title);
        $this->assertStringNotContainsString('faithflow_output', $contentItem->title);
    }

    // --- §28: tenancy ---

    public function test_handoff_requires_the_active_church_to_match_the_output(): void
    {
        $otherChurch = Church::create(['name' => 'Other Church', 'slug' => 'handoff-other-church-'.uniqid()]);
        $output = $this->generatedOutput(FaithFlowOutputType::DEVOTIONAL);

        $this->actingAs(User::factory()->forChurch($otherChurch, [ChurchRole::COMMUNICATIONS])->create());

        $this->expectException(LogicException::class);

        app(CreateContentItemFromFaithFlow::class)->handle($output);
    }

    // --- §34/§54: body cannot be blank ---

    public function test_handoff_rejects_an_output_with_blank_content(): void
    {
        $output = $this->generatedOutput(FaithFlowOutputType::DEVOTIONAL);
        $output->forceFill(['content' => '   '])->save();

        $this->expectException(LogicException::class);

        app(CreateContentItemFromFaithFlow::class)->handle($output->fresh());
    }
}
