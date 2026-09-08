<?php

namespace Database\Factories;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
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
            'tenant_id' => null,
            'is_super_admin' => false,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the account is on the product team.
     */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_super_admin' => true,
        ]);
    }

    /**
     * Put the account in a restaurant, roster and all.
     *
     * The tenant column says where the account belongs and the roster is what
     * opens that restaurant's panel, so a state that set only one of them would
     * describe a user the application cannot actually produce.
     */
    public function ofRestaurant(Restaurant $restaurant): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $restaurant->getKey(),
        ])->afterCreating(function (User $user) use ($restaurant): void {
            $user->restaurants()->syncWithoutDetaching([$restaurant->getKey()]);
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }
}
