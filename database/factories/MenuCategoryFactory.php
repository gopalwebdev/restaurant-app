<?php

namespace Database\Factories;

use App\Models\MenuCategory;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuCategory>
 */
class MenuCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'name' => fake()->unique()->randomElement([
                'Starters', 'Soups', 'Biryani', 'Breads', 'Curries',
                'Rice', 'Desserts', 'Beverages', 'Tandoori', 'Chinese',
            ]).' '.fake()->unique()->numberBetween(1, 9999),
            'position' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }

    /**
     * A category kept off the menu without being deleted.
     */
    public function hidden(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
