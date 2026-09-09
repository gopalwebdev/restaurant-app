<?php

namespace Database\Factories;

use App\Enums\Locale;
use App\Models\Menu;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Menu>
 */
class MenuFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Lunch', 'Dinner', 'Breakfast', 'Drinks', 'Desserts', 'Weekend Special',
        ]).' '.fake()->unique()->numberBetween(1, 9999);

        // English only by default: a menu with no Tamil copy yet is the normal
        // state of a restaurant that has just been onboarded, and the guest app
        // has to read correctly in that state.
        return [
            'restaurant_id' => Restaurant::factory(),
            'name' => [Locale::English->value => $name],
            'description' => null,
            'position' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }

    /**
     * A menu with every language filled in.
     */
    public function translated(): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => [
                Locale::English->value => $attributes['name'][Locale::English->value],
                Locale::Tamil->value => 'மெனு '.fake()->unique()->numberBetween(1, 9999),
            ],
        ]);
    }

    /**
     * A menu kept off the storefront without being deleted.
     */
    public function hidden(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
