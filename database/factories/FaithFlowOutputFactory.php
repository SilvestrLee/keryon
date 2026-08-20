<?php

namespace Database\Factories;

use App\Enums\FaithFlowOutputStatus;
use App\Enums\FaithFlowOutputType;
use App\Models\Church;
use App\Models\FaithFlowOutput;
use App\Models\FaithFlowRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FaithFlowOutput>
 */
class FaithFlowOutputFactory extends Factory
{
    protected $model = FaithFlowOutput::class;

    public function definition(): array
    {
        return [
            'church_id' => Church::factory(),
            'faithflow_run_id' => FaithFlowRun::factory(),
            'output_type' => FaithFlowOutputType::DEVOTIONAL,
            'status' => FaithFlowOutputStatus::PENDING,
        ];
    }

    /**
     * Explicitly set the owning Church, matching FaithFlowRunFactory's own
     * forChurch() convention — also sets the run to the same Church so the
     * fixture stays internally consistent.
     */
    public function forChurch(Church $church): static
    {
        return $this->state(fn (array $attributes) => [
            'church_id' => $church->id,
            'faithflow_run_id' => FaithFlowRun::factory()->forChurch($church),
        ]);
    }

    public function forRun(FaithFlowRun $run): static
    {
        return $this->state(fn (array $attributes) => [
            'church_id' => $run->church_id,
            'faithflow_run_id' => $run->id,
        ]);
    }

    public function generating(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FaithFlowOutputStatus::GENERATING,
        ]);
    }

    public function generated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FaithFlowOutputStatus::GENERATED,
            'generated_content' => $this->faker->paragraph(),
            'content' => $this->faker->paragraph(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FaithFlowOutputStatus::FAILED,
            'error_message' => 'Simulated generation failure.',
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FaithFlowOutputStatus::APPROVED,
            'generated_content' => $this->faker->paragraph(),
            'content' => $this->faker->paragraph(),
            'approved_at' => now(),
        ]);
    }
}
