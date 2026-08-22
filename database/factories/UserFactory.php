<?php

namespace Database\Factories;

use App\Enums\ChurchRole;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Give this User an active ChurchMembership in $church with the given
     * responsibility roles. Also mirrors church_id — the legacy
     * compatibility bridge (Blueprint v1.4.1 §11) — so existing test
     * assertions against $user->church_id remain valid during the
     * transition.
     *
     * @param  list<\App\Enums\ChurchRole|string>  $roles
     */
    public function forChurch(Church $church, array $roles = [], bool $primary = false): static
    {
        return $this->state(fn (array $attributes) => [
            'church_id' => $church->id,
        ])->afterCreating(function (User $user) use ($church, $roles, $primary) {
            $membership = ChurchMembership::factory()
                ->for($church)
                ->for($user)
                ->state(['is_primary' => $primary])
                ->create();

            $membership->assignRoles($roles);
        });
    }

    /**
     * The person who created $church — Primary Administrator, with the
     * full onboarding-default responsibility bundle. See Blueprint v1.4.1
     * preflight decision #3.
     *
     * @param  list<\App\Enums\ChurchRole|string>  $roles
     */
    public function asPrimaryAdministratorOf(
        Church $church,
        array $roles = [ChurchRole::ADMINISTRATOR, ChurchRole::COMMUNICATIONS, ChurchRole::CARE],
    ): static {
        return $this->forChurch($church, $roles, primary: true);
    }
}
