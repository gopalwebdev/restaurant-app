<?php

use App\Models\Restaurant;
use Inertia\Testing\AssertableInertia;

it('serves the guest home screen from a restaurant\'s own subdomain', function (): void {
    $restaurant = Restaurant::factory()->create([
        'slug' => 't1',
        'name' => 'Tenant One',
    ]);

    // A guest scanning a QR code lands on the tiles the restaurant arranged,
    // and walks from there into a menu or a PDF.
    $this->get('http://t1.restaurant-app.test/')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('home')
            ->where('restaurant.name', $restaurant->name)
            ->where('restaurant.slug', 't1')
            ->has('tiles', 0),
        );
});

it('serves the marketing page on the root domain', function (): void {
    $this->get('http://restaurant-app.test/')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('welcome'));
});

it('returns 404 for a subdomain with no restaurant behind it', function (): void {
    $this->get('http://nope.restaurant-app.test/')->assertNotFound();
});

it('takes an inactive restaurant storefront offline', function (): void {
    Restaurant::factory()->inactive()->create(['slug' => 'closed']);

    $this->get('http://closed.restaurant-app.test/')->assertNotFound();
});
