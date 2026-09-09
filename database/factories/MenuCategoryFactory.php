<?php

namespace Database\Factories;

use App\Enums\Locale;
use App\Models\Menu;
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
        $name = fake()->unique()->randomElement([
            'Starters', 'Soups', 'Biryani', 'Breads', 'Curries',
            'Rice', 'Desserts', 'Beverages', 'Tandoori', 'Chinese',
        ]).' '.fake()->unique()->numberBetween(1, 9999);

        return [
            // The restaurant is chosen first and the menu follows it, rather
            // than the other way round, so that passing a restaurant_id — which
            // most tests do — cannot produce a menu at a different restaurant
            // and trip the composite foreign key.
            'restaurant_id' => Restaurant::factory(),
            'menu_id' => fn (array $attributes): int => Menu::factory()
                ->create(['restaurant_id' => $attributes['restaurant_id']])
                ->getKey(),
            'name' => [Locale::English->value => $name],
            'position' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }

    /**
     * Put this section on an existing menu, and its restaurant with it.
     */
    public function inMenu(Menu $menu): static
    {
        return $this->state(fn (array $attributes): array => [
            'menu_id' => $menu->getKey(),
            'restaurant_id' => $menu->restaurant_id,
        ]);
    }

    /**
     * A section with every language filled in.
     */
    public function translated(): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => [
                Locale::English->value => $attributes['name'][Locale::English->value],
                Locale::Tamil->value => 'பிரிவு '.fake()->unique()->numberBetween(1, 9999),
            ],
        ]);
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
