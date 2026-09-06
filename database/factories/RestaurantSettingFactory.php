<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Models\Restaurant;
use App\Models\RestaurantSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantSetting>
 */
class RestaurantSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'contact_email' => fake()->unique()->companyEmail(),
            'contact_phone' => fake()->numerify('+91 ##### #####'),
            'timezone' => 'Asia/Kolkata',
            'currency' => Currency::IndianRupee,
            'accepts_orders' => true,
            'opens_at' => '09:00:00',
            'closes_at' => '23:00:00',
        ];
    }

    /**
     * Indicate that the restaurant is not taking orders.
     */
    public function closedForOrders(): static
    {
        return $this->state(fn (array $attributes): array => [
            'accepts_orders' => false,
        ]);
    }
}
