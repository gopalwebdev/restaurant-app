<?php

namespace Database\Factories;

use App\Enums\FoodType;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // The category brings the restaurant with it. Letting the two be
        // chosen independently would trip the composite foreign key, which is
        // exactly the mistake that key exists to catch.
        $category = MenuCategory::factory();

        return [
            'menu_category_id' => $category,
            'restaurant_id' => fn (array $attributes): int => MenuCategory::query()
                ->whereKey($attributes['menu_category_id'])
                ->value('restaurant_id'),
            'name' => ucfirst(fake()->unique()->word()).' '.fake()->unique()->numberBetween(1, 9999),
            'description' => fake()->optional()->sentence(),
            'price_minor_units' => fake()->numberBetween(5000, 90000),
            'food_type' => fake()->randomElement(FoodType::cases()),
            'is_available' => true,
            'position' => fake()->numberBetween(0, 20),
        ];
    }

    /**
     * Put this item on an existing category, and its restaurant with it.
     */
    public function inCategory(MenuCategory $category): static
    {
        return $this->state(fn (array $attributes): array => [
            'menu_category_id' => $category->getKey(),
            'restaurant_id' => $category->restaurant_id,
        ]);
    }

    /**
     * Sold out for now.
     */
    public function unavailable(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_available' => false,
        ]);
    }
}
