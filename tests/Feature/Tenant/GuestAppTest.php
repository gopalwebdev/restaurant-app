<?php

use App\Enums\Appearance;
use App\Enums\ItemAvailability;
use App\Enums\Locale;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuCombo;
use App\Models\MenuComboItem;
use App\Models\MenuItem;
use App\Models\MenuItemAddition;
use App\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;

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

it('leads a menu with the dishes the restaurant featured', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['tenant_id' => $restaurant->getKey()]);
    $category = MenuCategory::factory()->inMenu($menu)->create();

    $second = MenuItem::factory()->inCategory($category)->create([
        'name' => [Locale::English->value => 'Rasmalai'],
        'is_featured' => true,
        'featured_position' => 2,
    ]);
    $first = MenuItem::factory()->inCategory($category)->create([
        'name' => [Locale::English->value => 'Paneer Tikka'],
        'is_featured' => true,
        'featured_position' => 1,
    ]);
    $plain = MenuItem::factory()->inCategory($category)->create();

    $this->get('http://'.$restaurant->slug.'.restaurant-app.test/menus/'.$menu->getKey())
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('featured', 2)
            ->where('featured.0.id', $first->getKey())
            ->where('featured.0.name', 'Paneer Tikka')
            ->where('featured.1.id', $second->getKey())
            // A featured dish still appears under its own section, so a guest
            // scrolling down finds it where they expect it.
            ->has('sections.0.items', 3),
        );
});

it('leaves a sold-out dish out of the featured row', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['tenant_id' => $restaurant->getKey()]);
    $category = MenuCategory::factory()->inMenu($menu)->create();

    MenuItem::factory()->inCategory($category)->create([
        'is_featured' => true,
        'availability' => ItemAvailability::OutOfStock,
    ]);
    $available = MenuItem::factory()->inCategory($category)->create(['is_featured' => true]);

    $this->get('http://'.$restaurant->slug.'.restaurant-app.test/menus/'.$menu->getKey())
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('featured', 1)
            ->where('featured.0.id', $available->getKey()),
        );
});

/*
|--------------------------------------------------------------------------
| Sub-categories, combos and the small print
|--------------------------------------------------------------------------
|
| The whole menu comes down in one response: categories, their subdivisions,
| the dishes in each, and the combos the menu leads with.
|
*/

it('nests a category\'s subdivisions under it, its own dishes first', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['tenant_id' => $restaurant->getKey()]);
    $category = MenuCategory::factory()->inMenu($menu)->create(['name' => [Locale::English->value => 'Biryani']]);

    $chicken = MenuCategory::factory()->under($category)->create([
        'name' => [Locale::English->value => 'Chicken'],
        'position' => 0,
    ]);
    $mutton = MenuCategory::factory()->under($category)->create([
        'name' => [Locale::English->value => 'Mutton'],
        'position' => 1,
    ]);

    $direct = MenuItem::factory()->inCategory($category)->create(['name' => [Locale::English->value => 'Plain Biryani']]);
    $inChicken = MenuItem::factory()->inCategory($chicken)->create();
    $inMutton = MenuItem::factory()->inCategory($mutton)->create();

    $this->get(guestMenuUrl($restaurant, $menu))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('sections', 1)
            ->where('sections.0.name', 'Biryani')
            // The dishes filed straight under the category, and only those.
            ->has('sections.0.items', 1)
            ->where('sections.0.items.0.id', $direct->getKey())
            ->has('sections.0.subSections', 2)
            ->where('sections.0.subSections.0.name', 'Chicken')
            ->where('sections.0.subSections.0.items.0.id', $inChicken->getKey())
            ->where('sections.0.subSections.1.name', 'Mutton')
            ->where('sections.0.subSections.1.items.0.id', $inMutton->getKey()),
        );
});

it('leaves out a hidden sub-category and an empty category entirely', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['tenant_id' => $restaurant->getKey()]);

    $category = MenuCategory::factory()->inMenu($menu)->create();
    $hidden = MenuCategory::factory()->under($category)->hidden()->create();
    MenuItem::factory()->inCategory($hidden)->create();

    // A category whose only dishes are in a hidden subdivision has nothing
    // left to read, so it is not sent as an empty heading.
    $this->get(guestMenuUrl($restaurant, $menu))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->has('sections', 0));
});

it('sends the combos a menu leads with, in the order they were arranged', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['tenant_id' => $restaurant->getKey()]);
    $category = MenuCategory::factory()->inMenu($menu)->create();
    $burger = MenuItem::factory()->inCategory($category)->create(['name' => [Locale::English->value => 'Burger']]);

    $second = MenuCombo::factory()->onMenu($menu)->create([
        'name' => [Locale::English->value => 'Lunch Box'],
        'position' => 2,
    ]);
    $first = MenuCombo::factory()->onMenu($menu)->discounted()->create([
        'name' => [Locale::English->value => 'Burger Meal'],
        'position' => 1,
    ]);
    MenuComboItem::factory()->pairing($first, $burger)->quantity(2)->create();

    $soldOut = MenuCombo::factory()->onMenu($menu)->unavailable()->create();

    $this->get(guestMenuUrl($restaurant, $menu))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('combos', 2)
            ->where('combos.0.id', $first->getKey())
            ->where('combos.0.name', 'Burger Meal')
            ->where('combos.0.compareAtPriceMinorUnits', $first->compare_at_price_minor_units)
            ->has('combos.0.contents', 1)
            ->where('combos.0.contents.0.name', 'Burger')
            ->where('combos.0.contents.0.quantity', 2)
            ->where('combos.1.id', $second->getKey()),
        )
        // A combo that cannot be ordered is absent rather than greyed out,
        // exactly as a sold-out dish is — and there are only two here, so the
        // count above already says the third was left out.
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('combos.0.id', $first->getKey())
            ->where('combos.1.id', $second->getKey()));

    expect($soldOut->availability->isOrderable())->toBeFalse();
});

it('sends a struck-through price only when there is a real offer', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['tenant_id' => $restaurant->getKey()]);
    $category = MenuCategory::factory()->inMenu($menu)->create();

    $onOffer = MenuItem::factory()->inCategory($category)->create([
        'name' => [Locale::English->value => 'A Discounted'],
        'price_minor_units' => 29900,
        'compare_at_price_minor_units' => 36000,
        'position' => 0,
    ]);
    MenuItem::factory()->inCategory($category)->create([
        'name' => [Locale::English->value => 'B Plain'],
        'position' => 1,
    ]);

    $this->get(guestMenuUrl($restaurant, $menu))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('sections.0.items.0.id', $onOffer->getKey())
            ->where('sections.0.items.0.compareAtPriceMinorUnits', 36000)
            // Null rather than the stored value, so the app never has to judge
            // whether what it was handed is believable.
            ->where('sections.0.items.1.compareAtPriceMinorUnits', null),
        );
});

it('tells a guest what the prices do not include before they order', function (): void {
    $restaurant = Restaurant::factory()->create();
    $restaurant->settings->update([
        'tax_rate_basis_points' => 500,
        'prices_include_tax' => false,
        'service_charge_enabled' => true,
        'service_charge_basis_points' => 1000,
        'parcel_charge_enabled' => false,
        'parcel_charge_minor_units' => 2000,
    ]);

    $menu = Menu::factory()->create(['tenant_id' => $restaurant->getKey()]);

    $this->get(guestMenuUrl($restaurant, $menu))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('charges.taxRateBasisPoints', 500)
            ->where('charges.pricesIncludeTax', false)
            ->where('charges.serviceChargeBasisPoints', 1000)
            // A charge that is switched off arrives as null rather than its
            // amount, so the app has nothing to decide.
            ->where('charges.parcelChargeMinorUnits', null),
        );
});

it('says when a timed menu is being served, and when it is not', function (): void {
    $restaurant = Restaurant::factory()->create();
    $breakfast = Menu::factory()->servedBetween('07:00', '11:00')->create(['tenant_id' => $restaurant->getKey()]);

    $this->travelTo(now()->setTime(9, 0));

    $this->get(guestMenuUrl($restaurant, $breakfast))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            // HH:MM whichever driver stored it, so the app has one shape.
            ->where('menu.servedFrom', '07:00')
            ->where('menu.servedUntil', '11:00')
            ->where('menu.isBeingServed', true),
        );

    $this->travelTo(now()->setTime(15, 0));

    // Still served, still readable — a guest looking for the breakfast card at
    // three should find it rather than conclude the restaurant has none.
    $this->get(guestMenuUrl($restaurant, $breakfast))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('menu.isBeingServed', false),
        );
});

it('sends no service window for a menu that has none', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['tenant_id' => $restaurant->getKey()]);

    $this->get(guestMenuUrl($restaurant, $menu))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('menu.servedFrom', null)
            ->where('menu.servedUntil', null)
            ->where('menu.isBeingServed', true),
        );
});

it('sends the menu in the order the restaurant dragged it into', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['tenant_id' => $restaurant->getKey()]);

    // Positions deliberately run against creation order and against
    // alphabetical, so only the dragged order can produce the result.
    $second = MenuCategory::factory()->inMenu($menu)->create([
        'name' => [Locale::English->value => 'A Second'],
        'position' => 1,
    ]);
    $first = MenuCategory::factory()->inMenu($menu)->create([
        'name' => [Locale::English->value => 'Z First'],
        'position' => 0,
    ]);

    $subSecond = MenuCategory::factory()->under($first)->create([
        'name' => [Locale::English->value => 'A Sub Second'],
        'position' => 1,
    ]);
    $subFirst = MenuCategory::factory()->under($first)->create([
        'name' => [Locale::English->value => 'Z Sub First'],
        'position' => 0,
    ]);

    $dishSecond = MenuItem::factory()->inCategory($first)->create([
        'name' => [Locale::English->value => 'A Dish Second'],
        'position' => 1,
    ]);
    $dishFirst = MenuItem::factory()->inCategory($first)->create([
        'name' => [Locale::English->value => 'Z Dish First'],
        'position' => 0,
    ]);

    MenuItem::factory()->inCategory($subFirst)->create();
    MenuItem::factory()->inCategory($subSecond)->create();
    // A section with nothing in it is left out as an empty heading, so the
    // second one needs a dish to be in the payload at all.
    MenuItem::factory()->inCategory($second)->create();

    $this->get(guestMenuUrl($restaurant, $menu))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            // Sections in their dragged order.
            ->where('sections.0.name', 'Z First')
            ->where('sections.1.name', 'A Second')
            // A section's own dishes in theirs.
            ->where('sections.0.items.0.id', $dishFirst->getKey())
            ->where('sections.0.items.1.id', $dishSecond->getKey())
            // And its subdivisions in theirs.
            ->where('sections.0.subSections.0.name', 'Z Sub First')
            ->where('sections.0.subSections.1.name', 'A Sub Second'),
        );
});

it('sends a dish\'s additions in the order they were dragged into', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['tenant_id' => $restaurant->getKey()]);
    $category = MenuCategory::factory()->inMenu($menu)->create();
    $dish = MenuItem::factory()->inCategory($category)->create();

    $second = MenuItemAddition::factory()->onItem($dish)->create([
        'name' => [Locale::English->value => 'A Second'],
        'position' => 1,
    ]);
    $first = MenuItemAddition::factory()->onItem($dish)->create([
        'name' => [Locale::English->value => 'Z First'],
        'position' => 0,
    ]);

    // Additions are dragged inside the dish that owns them, and a guest reads
    // them in that order — the same rule as every other list on the menu.
    $this->get(guestMenuUrl($restaurant, $menu))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('sections.0.items.0.additions.0.id', $first->getKey())
            ->where('sections.0.items.0.additions.1.id', $second->getKey()),
        );
});
