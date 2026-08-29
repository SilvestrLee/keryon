<?php

namespace Tests\Feature\Trust;

use App\Enums\AiCapability;
use App\Enums\AiDataOrigin;
use App\Enums\ChurchRole;
use App\Enums\DataClassification;
use App\FaithFlow\Actions\AnalyzeFaithFlowSource;
use App\FaithFlow\Ai\CanonicalAnalysisAgent;
use App\FaithFlow\FaithFlowAi;
use App\Models\Church;
use App\Models\FaithFlowRun;
use App\Models\PrayerRequest;
use App\Models\User;
use App\Support\TenantContext;
use App\Trust\Ai\AiProcessingDeniedException;
use App\Trust\Ai\AiProcessingPolicy;
use App\Trust\Ai\AiProcessingRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiProcessingBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    private User $communicationsUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->church = Church::factory()->create();
        $this->communicationsUser = User::factory()
            ->forChurch($this->church, [ChurchRole::COMMUNICATIONS])
            ->create();
        $this->actingAs($this->communicationsUser);
    }

    public function test_known_prayer_request_text_is_denied_before_provider_prompt(): void
    {
        $text = str_repeat('A private pastoral prayer request. ', 5);
        $prayer = new PrayerRequest(['request' => $text]);
        $prayer->church_id = $this->church->id;
        $prayer->save();
        $run = $this->faithFlowRun($text);
        CanonicalAnalysisAgent::fake();

        try {
            app(FaithFlowAi::class)->analyze($run);
            $this->fail('Known Care content was not denied.');
        } catch (AiProcessingDeniedException $exception) {
            $this->assertSame('known_care_content', $exception->reason);
            $this->assertStringNotContainsString($text, $exception->getMessage());
        }

        CanonicalAnalysisAgent::assertNeverPrompted();
    }

    public function test_processing_denial_is_not_retried_and_records_only_bounded_metadata(): void
    {
        $text = str_repeat('A confidential Care request that must remain private. ', 4);
        $prayer = new PrayerRequest(['request' => $text]);
        $prayer->church_id = $this->church->id;
        $prayer->save();
        $run = $this->faithFlowRun($text);
        CanonicalAnalysisAgent::fake();

        $result = app(AnalyzeFaithFlowSource::class)->handle($run);

        $this->assertSame(1, $result->analysis_attempts);
        $this->assertSame('This information cannot be processed by AI for the requested purpose.', $result->analysis_error);
        $this->assertDatabaseHas('faithflow_usage', [
            'faithflow_run_id' => $run->id,
            'status' => 'failed',
            'error_category' => 'processing_denied',
        ]);
        $this->assertDatabaseCount('faithflow_usage', 1);
        $this->assertDatabaseMissing('faithflow_usage', ['error_category' => $text]);
        CanonicalAnalysisAgent::assertNeverPrompted();
    }

    public function test_roles_and_primary_status_do_not_override_care_prohibition(): void
    {
        $cases = [
            'Care' => [[ChurchRole::CARE], false],
            'Administrator' => [[ChurchRole::ADMINISTRATOR], false],
            'Primary administrator' => [[ChurchRole::ADMINISTRATOR], true],
            'Communications plus Care' => [[ChurchRole::COMMUNICATIONS, ChurchRole::CARE], false],
        ];

        foreach ($cases as $label => [$roles, $primary]) {
            $user = User::factory()->forChurch($this->church, $roles, primary: $primary)->create();
            $this->actingAs($user);
            app(TenantContext::class)->forgetResolved();
            $this->assertDenied(
                in_array(ChurchRole::COMMUNICATIONS, $roles, true) ? 'prohibited_data' : 'capability_denied',
                fn () => $this->policy(AiDataOrigin::Care, DataClassification::HighlyRestricted),
            );
        }
    }

    public function test_unknown_provider_is_denied(): void
    {
        $this->assertDenied('unknown_provider', fn () => $this->policy(provider: 'tenant-supplied-provider'));
    }

    public function test_unapproved_provider_is_denied_even_when_credentials_could_exist(): void
    {
        config()->set('ai-governance.providers.anthropic.status', 'under_review');

        $this->assertDenied('provider_not_approved', fn () => $this->policy());
    }

    public function test_unresolved_provider_governance_fails_closed(): void
    {
        config()->set('ai-governance.providers.anthropic.region', 'unknown');

        $this->assertDenied('provider_governance_unresolved', fn () => $this->policy());
    }

    public function test_unaccepted_contract_or_dpa_fails_closed(): void
    {
        config()->set('ai-governance.providers.anthropic.contract_status', 'unverified');
        $this->assertDenied('provider_contract_unresolved', fn () => $this->policy());

        config()->set('ai-governance.providers.anthropic.contract_status', 'accepted');
        config()->set('ai-governance.providers.anthropic.dpa_status', 'unverified');
        $this->assertDenied('provider_contract_unresolved', fn () => $this->policy());
    }

    public function test_restricted_provider_obeys_the_same_bounded_capability_contract(): void
    {
        config()->set('ai-governance.providers.anthropic.status', 'restricted');
        $this->policy();
        $this->addToAssertionCount(1);

        $this->assertDenied(
            'model_not_approved',
            fn () => $this->policy(model: 'unreviewed-model'),
        );
    }

    public function test_unapproved_capability_and_model_are_denied(): void
    {
        $capabilities = config('ai-governance.providers.anthropic.capabilities');
        unset($capabilities['faithflow.generation']);
        config()->set('ai-governance.providers.anthropic.capabilities', $capabilities);
        $this->assertDenied('capability_not_approved', fn () => $this->policy(capability: AiCapability::FaithFlowGeneration));

        try {
            $this->policy(model: 'tenant-selected-model');
            $this->fail('An unapproved model was not denied.');
        } catch (AiProcessingDeniedException $exception) {
            $this->assertSame('model_not_approved', $exception->reason);
        }
    }

    public function test_approved_provider_capability_model_and_classification_may_proceed(): void
    {
        $this->policy();
        $this->addToAssertionCount(1);
    }

    public function test_highly_restricted_data_and_media_are_denied(): void
    {
        $this->assertDenied('prohibited_data', fn () => $this->policy(classification: DataClassification::HighlyRestricted));

        try {
            $this->policy(containsMedia: true);
            $this->fail('Media was not denied.');
        } catch (AiProcessingDeniedException $exception) {
            $this->assertSame('media_not_approved', $exception->reason);
        }
    }

    public function test_cross_church_and_missing_context_are_denied(): void
    {
        $otherChurch = Church::factory()->create();
        $this->assertDenied('invalid_tenant_context', fn () => $this->policy(churchId: $otherChurch->id));

        auth()->logout();
        app(TenantContext::class)->forgetResolved();

        try {
            $this->policy();
            $this->fail('Missing context was not denied.');
        } catch (AiProcessingDeniedException $exception) {
            $this->assertSame('invalid_tenant_context', $exception->reason);
        }
    }

    private function faithFlowRun(string $source): FaithFlowRun
    {
        return FaithFlowRun::factory()->forChurch($this->church)->create([
            'source_text' => $source,
            'source_char_count' => mb_strlen($source),
        ]);
    }

    private function policy(
        AiDataOrigin $origin = AiDataOrigin::FaithFlowSource,
        DataClassification $classification = DataClassification::Sensitive,
        AiCapability $capability = AiCapability::FaithFlowAnalysis,
        string $provider = 'anthropic',
        string $model = 'claude-sonnet-5',
        ?int $churchId = null,
        bool $containsMedia = false,
    ): void {
        app(AiProcessingPolicy::class)->authorize(new AiProcessingRequest(
            capability: $capability,
            classification: $classification,
            origin: $origin,
            churchId: $churchId ?? $this->church->id,
            provider: $provider,
            model: $model,
            containsMedia: $containsMedia,
        ));
    }

    private function assertDenied(string $reason, callable $operation): void
    {
        try {
            $operation();
            $this->fail("Expected AI processing denial [{$reason}].");
        } catch (AiProcessingDeniedException $exception) {
            $this->assertSame($reason, $exception->reason);
            $this->assertSame('This information cannot be processed by AI for the requested purpose.', $exception->getMessage());
        }
    }
}
