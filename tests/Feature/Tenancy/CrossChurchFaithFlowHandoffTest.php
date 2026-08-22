<?php

namespace Tests\Feature\Tenancy;

use App\Enums\ChurchRole;
use App\Enums\FaithFlowOutputStatus;
use App\Enums\FaithFlowOutputType;
use App\Enums\FaithFlowRunStatus;
use App\FaithFlow\Actions\ApproveFaithFlowOutput;
use App\FaithFlow\Actions\CreateContentItemFromFaithFlow;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\ContentItem;
use App\Models\FaithFlowOutput;
use App\Models\FaithFlowRun;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/**
 * K-FAITHFLOW-001E §28/§29/§57 — approval/handoff crosses into Content
 * Studio, so tenancy is verified here specifically for that path rather
 * than relying only on CrossChurchFaithFlowAccessTest's generic CRUD
 * coverage.
 */
class CrossChurchFaithFlowHandoffTest extends TestCase
{
    use RefreshDatabase;

    protected function generatedOutputFor(Church $church): FaithFlowOutput
    {
        $run = FaithFlowRun::factory()->forChurch($church)->create([
            'status' => FaithFlowRunStatus::ANALYZED,
            'canonical_analysis' => [
                'source_summary' => 'A sermon about hope.',
                'principal_message' => 'God remains faithful.',
                'key_themes' => ['hope'],
                'notable_statements' => [],
                'scripture_references' => [],
                'ministry_context' => 'Sunday sermon',
                'audience_clues' => null,
                'tone' => 'Encouraging',
            ],
        ]);

        return FaithFlowOutput::factory()->forRun($run)->create([
            'output_type' => FaithFlowOutputType::DEVOTIONAL,
            'status' => FaithFlowOutputStatus::GENERATED,
            'generated_content' => 'A generated devotional.',
            'content' => 'A generated devotional.',
        ]);
    }

    public function test_cross_church_output_cannot_be_approved(): void
    {
        $churchA = Church::create(['name' => 'Handoff Church A', 'slug' => 'handoff-tenancy-church-a']);
        $churchB = Church::create(['name' => 'Handoff Church B', 'slug' => 'handoff-tenancy-church-b']);

        $outputB = $this->generatedOutputFor($churchB);
        $userA = User::factory()->forChurch($churchA, [ChurchRole::COMMUNICATIONS])->create();

        $this->actingAs($userA);

        $this->assertFalse($userA->can('approve', $outputB));
    }

    public function test_cross_church_output_cannot_be_handed_off_even_if_action_is_invoked_directly(): void
    {
        $churchA = Church::create(['name' => 'Handoff Church C', 'slug' => 'handoff-tenancy-church-c']);
        $churchB = Church::create(['name' => 'Handoff Church D', 'slug' => 'handoff-tenancy-church-d']);

        $outputB = $this->generatedOutputFor($churchB);
        $userA = User::factory()->forChurch($churchA, [ChurchRole::COMMUNICATIONS])->create();

        $this->actingAs($userA);

        // Defense in depth: even bypassing the policy check entirely and
        // calling the domain action directly, the tenancy guard inside
        // CreateContentItemFromFaithFlow denies the mismatch itself.
        $this->expectException(LogicException::class);

        app(CreateContentItemFromFaithFlow::class)->handle($outputB);
    }

    /**
     * The mandatory multi-Church regression, applied to approve/handoff
     * specifically — mirrors FaithFlowAuthorizationTest's own precedent
     * (K-FAITHFLOW-001B §32).
     */
    public function test_approve_and_handoff_follow_the_active_church_not_the_user(): void
    {
        $churchA = Church::create(['name' => 'Multi Handoff Church A', 'slug' => 'multi-handoff-church-a']);
        $churchB = Church::create(['name' => 'Multi Handoff Church B', 'slug' => 'multi-handoff-church-b']);

        $user = User::factory()->create();

        ChurchMembership::factory()->for($user)->for($churchA)->create()->assignRoles([ChurchRole::COMMUNICATIONS]);
        ChurchMembership::factory()->for($user)->for($churchB)->create()->assignRoles([ChurchRole::CARE]);

        $this->actingAs($user);

        session(['active_church_id' => $churchA->id]);
        app(TenantContext::class)->forgetResolved();

        $outputA = $this->generatedOutputFor($churchA);
        $this->assertTrue($user->can('approve', $outputA));

        $result = app(ApproveFaithFlowOutput::class)->handle($outputA);
        $this->assertSame(FaithFlowOutputStatus::APPROVED, $result->status);
        $this->assertSame($user->id, $result->approved_by);
        $this->assertNotNull($result->content_item_id);

        session(['active_church_id' => $churchB->id]);
        app(TenantContext::class)->forgetResolved();

        // Church A's own output is now outside the active (Church B)
        // tenant scope entirely — invisible, not merely unauthorized.
        $this->assertNull(FaithFlowOutput::query()->find($outputA->id));

        $outputForB = $this->generatedOutputFor($churchB);
        $this->assertFalse($user->can('approve', $outputForB));
        $this->assertFalse($user->can('create', ContentItem::class));
    }
}
