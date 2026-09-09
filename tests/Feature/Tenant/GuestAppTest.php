<?php

use App\Enums\Appearance;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function guestUrl(Restaurant $restaurant): string
{
    return 'http://'.$restaurant->slug.'.restaurant-app.test/';
}

/*
|--------------------------------------------------------------------------
| The menu at the table
|--------------------------------------------------------------------------
*/

it('serves the menu with no sign-in', function (): void {
    $restaurant = Restaurant::factory()->create();
    $category = MenuCategory::factory()->create(['restaurant_id' => $restaurant->getKey()]);
    $item = MenuItem::factory()->inCategory($category)->create();

    $this->get(guestUrl($restaurant))
        ->assertOk()
        ->assertSee($item->name)
        ->assertSee($category->name);
});

it('leaves out what a guest cannot order', function (): void {
    $restaurant = Restaurant::factory()->create();
    $showing = MenuCategory::factory()->create(['restaurant_id' => $restaurant->getKey()]);
    $hidden = MenuCategory::factory()->hidden()->create(['restaurant_id' => $restaurant->getKey()]);

    $available = MenuItem::factory()->inCategory($showing)->create();
    $soldOut = MenuItem::factory()->inCategory($showing)->unavailable()->create();
    $inHidden = MenuItem::factory()->inCategory($hidden)->create();

    // A phone menu should not make someone scroll past things they cannot
    // have, so these are absent rather than greyed out.
    $this->get(guestUrl($restaurant))
        ->assertOk()
        ->assertSee($available->name)
        ->assertDontSee($soldOut->name)
        ->assertDontSee($inHidden->name);
});

it('shows only this restaurant\'s menu', function (): void {
    $mine = Restaurant::factory()->create();
    $theirs = Restaurant::factory()->create();

    $mineItem = MenuItem::factory()->inCategory(
        MenuCategory::factory()->create(['restaurant_id' => $mine->getKey()]),
    )->create();

    $theirsItem = MenuItem::factory()->inCategory(
        MenuCategory::factory()->create(['restaurant_id' => $theirs->getKey()]),
    )->create();

    $this->get(guestUrl($mine))
        ->assertOk()
        ->assertSee($mineItem->name)
        ->assertDontSee($theirsItem->name);
});

it('hides a restaurant that is switched off', function (): void {
    $restaurant = Restaurant::factory()->create(['is_active' => false]);

    $this->get(guestUrl($restaurant))->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Branding comes from the restaurant's own settings
|--------------------------------------------------------------------------
*/

it('paints both apps in the restaurant\'s colour', function (): void {
    $restaurant = Restaurant::factory()->create();
    $restaurant->settings->update([
        'theme_primary_color' => '#0EA5E9',
        'theme_appearance' => Appearance::Dark,
    ]);

    // In the first response's HTML, not an Inertia prop: React runs after the
    // paint, so a prop would show the default colour and then correct itself.
    $this->get(guestUrl($restaurant))
        ->assertOk()
        ->assertSee('--primary: #0EA5E9', escape: false)
        ->assertSee('"dark"', escape: false);

    $this->get('http://'.$restaurant->slug.'.restaurant-app.test/staff/login')
        ->assertOk()
        ->assertSee('--primary: #0EA5E9', escape: false);
});

/*
|--------------------------------------------------------------------------
| The two apps never load each other, or Filament
|--------------------------------------------------------------------------
*/

it('loads only its own entry and page', function (): void {
    // Built assets are hashed, so the guarantee has to be checked against the
    // manifest rather than against source paths, which only appear in dev.
    $manifestPath = public_path('build/manifest.json');

    if (! file_exists($manifestPath)) {
        $this->markTestSkipped('Run `npm run build` to check the built asset split.');
    }

    $manifest = json_decode((string) file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
    $chunk = fn (string $source): string => basename($manifest[$source]['file']);

    $restaurant = Restaurant::factory()->create();

    $guest = $this->get(guestUrl($restaurant))->assertOk()->getContent();

    expect($guest)->toContain($chunk('resources/js/guest.tsx'))
        ->and($guest)->toContain($chunk('resources/js/pages/guest/menu.tsx'))
        ->and($guest)->not->toContain($chunk('resources/js/staff.tsx'))
        ->and($guest)->not->toContain($chunk('resources/js/pages/staff/login.tsx'))
        ->and($guest)->not->toContain($chunk('resources/js/app.tsx'));

    $staff = $this->get('http://'.$restaurant->slug.'.restaurant-app.test/staff/login')
        ->assertOk()->getContent();

    expect($staff)->toContain($chunk('resources/js/staff.tsx'))
        ->and($staff)->toContain($chunk('resources/js/pages/staff/login.tsx'))
        ->and($staff)->not->toContain($chunk('resources/js/guest.tsx'))
        ->and($staff)->not->toContain($chunk('resources/js/pages/guest/menu.tsx'));
});

it('makes only the staff app installable', function (): void {
    $restaurant = Restaurant::factory()->create();

    // Staff install this and keep it; guests arrive by QR and leave, so an
    // install prompt at a table would be noise. See .ai/rules/js.md.
    $this->get('http://'.$restaurant->slug.'.restaurant-app.test/staff/login')
        ->assertSee('rel="manifest"', escape: false)
        ->assertSee('serviceWorker', escape: false);

    $this->get(guestUrl($restaurant))
        ->assertDontSee('rel="manifest"', escape: false)
        ->assertDontSee('serviceWorker', escape: false);
});
