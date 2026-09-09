<?php

use App\Enums\Appearance;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemAddition;
use App\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function guestUrl(Restaurant $restaurant): string
{
    return 'http://'.$restaurant->slug.'.restaurant-app.test/';
}

function guestMenuUrl(Restaurant $restaurant, Menu $menu): string
{
    return 'http://'.$restaurant->slug.'.restaurant-app.test/menus/'.$menu->getKey();
}

/**
 * A menu with one section holding one dish, for the restaurant given.
 *
 * @return array{Menu, MenuCategory, MenuItem}
 */
function seedOneDish(Restaurant $restaurant): array
{
    $menu = Menu::factory()->create(['tenant_id' => $restaurant->getKey()]);
    $category = MenuCategory::factory()->inMenu($menu)->create();
    $item = MenuItem::factory()->inCategory($category)->create();

    return [$menu, $category, $item];
}

/*
|--------------------------------------------------------------------------
| The menu at the table
|--------------------------------------------------------------------------
*/

it('serves the menu with no sign-in', function (): void {
    $restaurant = Restaurant::factory()->create();
    [$menu, $category, $item] = seedOneDish($restaurant);

    $this->get(guestMenuUrl($restaurant, $menu))
        ->assertOk()
        ->assertSee($item->name)
        ->assertSee($category->name);
});

it('leaves out what a guest cannot order', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['tenant_id' => $restaurant->getKey()]);

    $showing = MenuCategory::factory()->inMenu($menu)->create();
    $hidden = MenuCategory::factory()->inMenu($menu)->hidden()->create();

    $available = MenuItem::factory()->inCategory($showing)->create();
    $soldOut = MenuItem::factory()->inCategory($showing)->unavailable()->create();
    $inHidden = MenuItem::factory()->inCategory($hidden)->create();

    $offered = MenuItemAddition::factory()->onItem($available)->create();
    $runOut = MenuItemAddition::factory()->onItem($available)->unavailable()->create();

    // A phone menu should not make someone scroll past things they cannot
    // have, so these are absent rather than greyed out.
    $this->get(guestMenuUrl($restaurant, $menu))
        ->assertOk()
        ->assertSee($available->name)
        ->assertSee($offered->name)
        ->assertDontSee($soldOut->name)
        ->assertDontSee($inHidden->name)
        ->assertDontSee($runOut->name);
});

it('takes a whole hidden menu down, sections and dishes with it', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->hidden()->create(['tenant_id' => $restaurant->getKey()]);
    MenuItem::factory()->inCategory(MenuCategory::factory()->inMenu($menu)->create())->create();

    $this->get(guestMenuUrl($restaurant, $menu))->assertNotFound();
});

it('shows only this restaurant\'s menu', function (): void {
    $mine = Restaurant::factory()->create();
    $theirs = Restaurant::factory()->create();

    [$myMenu, , $mineItem] = seedOneDish($mine);
    [$theirMenu, , $theirsItem] = seedOneDish($theirs);

    $this->get(guestMenuUrl($mine, $myMenu))
        ->assertOk()
        ->assertSee($mineItem->name)
        ->assertDontSee($theirsItem->name);

    // The restaurant arrives in the domain rather than the path, so scoped
    // bindings do not cover this: the controller has to refuse it by hand.
    $this->get(guestMenuUrl($mine, $theirMenu))->assertNotFound();
});

it('hides a restaurant that is switched off', function (): void {
    $restaurant = Restaurant::factory()->create(['is_active' => false]);
    [$menu] = seedOneDish($restaurant);

    $this->get(guestUrl($restaurant))->assertNotFound();
    $this->get(guestMenuUrl($restaurant, $menu))->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Light or dark belongs to the phone, not to the restaurant
|--------------------------------------------------------------------------
*/

it('paints both apps light until the phone says otherwise', function (): void {
    $restaurant = Restaurant::factory()->create();

    // In the first response's HTML, not an Inertia prop: React runs after the
    // paint, so a prop would show one shade and then correct itself.
    $this->get(guestUrl($restaurant))
        ->assertOk()
        ->assertSee('"light"', escape: false);

    $this->get('http://'.$restaurant->slug.'.restaurant-app.test/staff/login')
        ->assertOk()
        ->assertSee('"light"', escape: false);
});

it('paints both apps dark when the phone has asked for dark', function (): void {
    $restaurant = Restaurant::factory()->create();

    // There is no per restaurant default and no brand colour: the whole of the
    // theming is this cookie, which is unencrypted so the toggle in React and
    // the server read the same value.
    $this->withUnencryptedCookie('appearance', Appearance::Dark->value)
        ->get(guestUrl($restaurant))
        ->assertOk()
        ->assertSee('"dark"', escape: false)
        ->assertDontSee('"light"', escape: false);

    $this->withUnencryptedCookie('appearance', Appearance::Dark->value)
        ->get('http://'.$restaurant->slug.'.restaurant-app.test/staff/login')
        ->assertOk()
        ->assertSee('"dark"', escape: false);
});

it('ignores a tampered appearance cookie rather than breaking the page', function (): void {
    $restaurant = Restaurant::factory()->create();

    // The cookie is unencrypted and therefore visitor-controlled.
    $this->withUnencryptedCookie('appearance', 'neon')
        ->get(guestUrl($restaurant))
        ->assertOk()
        ->assertSee('"light"', escape: false);
});

it('offers no theme customisation to the restaurant', function (): void {
    // Light and dark are the whole of it, so the columns that used to hold a
    // brand colour and a default are gone — see App\Enums\Appearance.
    expect(Schema::hasColumn('restaurant_settings', 'theme_primary_color'))->toBeFalse()
        ->and(Schema::hasColumn('restaurant_settings', 'theme_appearance'))->toBeFalse();
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
        ->and($guest)->toContain($chunk('resources/js/pages/guest/home.tsx'))
        ->and($guest)->not->toContain($chunk('resources/js/staff.tsx'))
        ->and($guest)->not->toContain($chunk('resources/js/pages/staff/login.tsx'))
        ->and($guest)->not->toContain($chunk('resources/js/app.tsx'));

    $staff = $this->get('http://'.$restaurant->slug.'.restaurant-app.test/staff/login')
        ->assertOk()->getContent();

    expect($staff)->toContain($chunk('resources/js/staff.tsx'))
        ->and($staff)->toContain($chunk('resources/js/pages/staff/login.tsx'))
        ->and($staff)->not->toContain($chunk('resources/js/guest.tsx'))
        ->and($staff)->not->toContain($chunk('resources/js/pages/guest/home.tsx'));
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
