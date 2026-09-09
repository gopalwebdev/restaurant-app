<?php

namespace Database\Factories;

use App\Enums\Locale;
use App\Models\MenuCategory;
use App\Models\MenuSubCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuSubCategory>
 */
class MenuSubCategoryFactory extends Factory
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

        $name = fake()->unique()->randomElement([
            'Chicken', 'Mutton', 'Vegetable', 'Prawn', 'Egg',
            'Paneer', 'Fish', 'Mushroom',
        ]).' '.fake()->unique()->numberBetween(1, 9999);

        return [
            'menu_category_id' => $category,
            'tenant_id' => fn (array $attributes): int => MenuCategory::query()
                ->whereKey($attributes['menu_category_id'])
                ->value('tenant_id'),
            'name' => [Locale::English->value => $name],
            'position' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }

    /**
     * Put this sub-category under an existing category, restaurant and all.
     */
    public function inCategory(MenuCategory $category): static
    {
        return $this->state(fn (array $attributes): array => [
            'menu_category_id' => $category->getKey(),
            'tenant_id' => $category->tenant_id,
        ]);
    }

    /**
     * A sub-category with every language filled in.
     */
    public function translated(): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => [
                Locale::English->value => $attributes['name'][Locale::English->value],
                Locale::Tamil->value => 'உட்பிரிவு '.fake()->unique()->numberBetween(1, 9999),
            ],
        ]);
    }

    /**
     * A sub-category kept off the menu without being deleted.
     */
    public function hidden(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
