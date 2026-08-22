<?php

namespace Database\Factories;

use App\Enums\MembershipStatus;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChurchMembership>
 */
class ChurchMembershipFactory extends Factory
{
    protected $model = ChurchMembership::class;

    public function definition(): array
    {
        return [
            'church_id' => Church::factory(),
            'user_id' => User::factory(),
            'status' => MembershipStatus::ACTIVE,
            'is_primary' => false,
            'joined_at' => now(),
        ];
    }

    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MembershipStatus::SUSPENDED,
        ]);
    }

    public function removed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MembershipStatus::REMOVED,
        ]);
    }
}
