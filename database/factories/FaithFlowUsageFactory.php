<?php

namespace Database\Factories;

use App\Models\Church;
use App\Models\FaithFlowRun;
use App\Models\FaithFlowUsage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FaithFlowUsage>
 */
class FaithFlowUsageFactory extends Factory
{
    protected $model = FaithFlowUsage::class;

    public function definition(): array
    {
        return [
            'church_id' => Church::factory(),
            'faithflow_run_id' => FaithFlowRun::factory(),
            'operation' => 'analysis',
            'provider' => 'anthropic',
            'model' => 'claude-sonnet-5',
            'input_tokens' => $this->faker->numberBetween(200, 4000),
            'output_tokens' => $this->faker->numberBetween(50, 800),
            'latency_ms' => $this->faker->numberBetween(400, 6000),
            'status' => 'success',
        ];
    }

    public function forRun(FaithFlowRun $run): static
    {
        return $this->state(fn (array $attributes) => [
            'church_id' => $run->church_id,
            'faithflow_run_id' => $run->id,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'error_category' => 'provider_unavailable',
        ]);
    }
}
