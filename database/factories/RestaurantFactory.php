<?php

namespace Database\Factories;

use App\Models\Restaurant;
use App\Models\RestaurantSetting;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Restaurant>
 */
class RestaurantFactory extends Factory
{
    protected $model = Restaurant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'is_active' => true,
        ];
    }

    /**
     * Every restaurant has settings, so one is made alongside it.
     *
     * The id is passed straight through rather than nesting a factory, which
     * would otherwise create a second restaurant to hang the settings off.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Restaurant $restaurant): void {
            RestaurantSetting::factory()->create([
                'restaurant_id' => $restaurant->getKey(),
            ]);
        });
    }

    /**
     * Indicate that the restaurant is closed and should not serve its storefront.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
