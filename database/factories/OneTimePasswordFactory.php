<?php

namespace Database\Factories;

use App\Models\OneTimePassword;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<OneTimePassword>
 */
class OneTimePasswordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes((int) config('otp.ttl')),
            'consumed_at' => null,
        ];
    }

    /**
     * Set the code this one-time password will accept.
     */
    public function code(string $code): static
    {
        return $this->state(fn (array $attributes): array => [
            'code_hash' => Hash::make($code),
        ]);
    }

    /**
     * Indicate that the code is past its lifetime.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    /**
     * Indicate that the code has already been spent.
     */
    public function consumed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'consumed_at' => now(),
        ]);
    }

    /**
     * Indicate that the code has used up its allowance of wrong guesses.
     */
    public function exhausted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'attempts' => (int) config('otp.max_attempts'),
        ]);
    }
}
