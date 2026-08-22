<?php

namespace Database\Factories;

use App\Enums\FaithFlowRunStatus;
use App\Models\Church;
use App\Models\FaithFlowRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FaithFlowRun>
 */
class FaithFlowRunFactory extends Factory
{
    protected $model = FaithFlowRun::class;

    public function definition(): array
    {
        $sourceText = $this->faker->paragraphs(6, true);

        return [
            'church_id' => Church::factory(),
            'created_by' => User::factory(),
            'source_text' => $sourceText,
            'source_char_count' => mb_strlen($sourceText),
            'status' => FaithFlowRunStatus::DRAFT,
        ];
    }

    /**
     * Explicitly set the owning Church rather than letting the factory
     * create one — needed for tests that construct records for a specific
     * Church without an authenticated TenantContext to auto-stamp it.
     */
    public function forChurch(Church $church): static
    {
        return $this->state(fn (array $attributes) => [
            'church_id' => $church->id,
        ]);
    }

    public function analyzing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FaithFlowRunStatus::ANALYZING,
        ]);
    }

    public function analyzed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FaithFlowRunStatus::ANALYZED,
        ]);
    }

    public function analysisFailed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FaithFlowRunStatus::ANALYSIS_FAILED,
            'analysis_error' => 'Simulated analysis failure.',
            'analysis_attempts' => 1,
        ]);
    }
}
