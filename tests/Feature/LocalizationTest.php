<?php

use App\Enums\Locale;
use App\Http\Middleware\SetLocale;
use App\Models\HomeTile;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemAddition;
use App\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * A restaurant with one bilingual dish on one bilingual menu.
 *
 * @return array{Restaurant, Menu, MenuCategory, MenuItem, MenuItemAddition}
 */
function seedBilingualMenu(): array
{
    $restaurant = Restaurant::factory()->create(['slug' => 'spice']);

    $menu = Menu::factory()->create([
        'restaurant_id' => $restaurant->getKey(),
        'name' => ['en' => 'Dinner', 'ta' => 'இரவு உணவு'],
    ]);

    $category = MenuCategory::factory()->inMenu($menu)->create([
        'name' => ['en' => 'Starters', 'ta' => 'தொடக்கங்கள்'],
    ]);

    $item = MenuItem::factory()->inCategory($category)->create([
        'name' => ['en' => 'Paneer Tikka', 'ta' => 'பன்னீர் டிக்கா'],
        'description' => ['en' => 'Charred in the tandoor.', 'ta' => 'தந்தூரில் சுடப்பட்டது.'],
    ]);

    $addition = MenuItemAddition::factory()->onItem($item)->create([
        'name' => ['en' => 'Extra paneer', 'ta' => 'கூடுதல் பன்னீர்'],
    ]);

    return [$restaurant, $menu, $category, $item, $addition];
}

function menuUrl(Restaurant $restaurant, Menu $menu): string
{
    return 'http://'.$restaurant->slug.'.restaurant-app.test/menus/'.$menu->getKey();
}

/*
|--------------------------------------------------------------------------
| English is the default and the fallback
|--------------------------------------------------------------------------
*/

it('answers in English when a visitor has chosen nothing', function (): void {
    [$restaurant, $menu, $category, $item] = seedBilingualMenu();

    $this->get(menuUrl($restaurant, $menu))
        ->assertOk()
        ->assertSee($menu->getTranslation('name', 'en'))
        ->assertSee($category->getTranslation('name', 'en'))
        ->assertSee($item->getTranslation('name', 'en'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('locale.current', Locale::English->value)
            // The toggle's destination is worked out server-side, so the button
            // does not need to know the list of languages.
            ->where('locale.next', Locale::Tamil->value)
            ->where('translations.status.open', 'Open'),
        );
});

it('falls back to English for a name that has no translation yet', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create([
        'restaurant_id' => $restaurant->getKey(),
        'name' => ['en' => 'Dinner'],
    ]);
    $item = MenuItem::factory()
        ->inCategory(MenuCategory::factory()->inMenu($menu)->create(['name' => ['en' => 'Starters']]))
        ->create(['name' => ['en' => 'Paneer Tikka']]);

    // A restaurant that has not translated its menu yet is the normal state on
    // day one, and a Tamil-reading guest must still get a readable menu.
    $this->withUnencryptedCookie(SetLocale::COOKIE, Locale::Tamil->value)
        ->get(menuUrl($restaurant, $menu))
        ->assertOk()
        ->assertSee('Paneer Tikka')
        ->assertSee('Starters');

    expect($item->getTranslation('name', Locale::Tamil->value))->toBe('Paneer Tikka');
});

/*
|--------------------------------------------------------------------------
| Switching language
|--------------------------------------------------------------------------
*/

it('remembers the chosen language in an unencrypted cookie', function (): void {
    $restaurant = Restaurant::factory()->create(['slug' => 'spice']);

    $response = $this->put(
        'http://spice.restaurant-app.test/preferences/language',
        ['locale' => Locale::Tamil->value],
    );

    $response->assertRedirect();

    // Unencrypted so the toggle in React and the middleware read the same
    // value, exactly as the appearance cookie already works.
    $cookie = collect($response->headers->getCookies())
        ->firstWhere(fn ($candidate): bool => $candidate->getName() === SetLocale::COOKIE);

    expect($cookie)->not->toBeNull()
        ->and($cookie->getValue())->toBe(Locale::Tamil->value)
        ->and($restaurant->slug)->toBe('spice');
});

it('answers in Tamil once the language has been chosen', function (): void {
    [$restaurant, $menu, $category, $item, $addition] = seedBilingualMenu();

    // Read through the props rather than the HTML: Inertia serialises its
    // payload as JSON, which escapes non-ASCII, so assertSee() would be looking
    // for Tamil in a document that spells it \u0ba4 and so on.
    $this->withUnencryptedCookie(SetLocale::COOKIE, Locale::Tamil->value)
        ->get(menuUrl($restaurant, $menu))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('locale.current', Locale::Tamil->value)
            ->where('locale.next', Locale::English->value)
            // The restaurant's own words, from translated columns...
            ->where('menu.name', $menu->getTranslation('name', 'ta'))
            ->where('sections.0.name', $category->getTranslation('name', 'ta'))
            ->where('sections.0.items.0.name', $item->getTranslation('name', 'ta'))
            ->where('sections.0.items.0.description', $item->getTranslation('description', 'ta'))
            ->where('sections.0.items.0.additions.0.name', $addition->getTranslation('name', 'ta'))
            // ...and the chrome, from lang/ta/guest.php.
            ->where('translations.status.open', 'திறந்துள்ளது'),
        );
});

it('translates the tiles on the home screen too', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['restaurant_id' => $restaurant->getKey()]);

    HomeTile::factory()->openingMenu($menu)->create([
        'label' => ['en' => 'Our menu', 'ta' => 'எங்கள் மெனு'],
    ]);

    $this->withUnencryptedCookie(SetLocale::COOKIE, Locale::Tamil->value)
        ->get('http://'.$restaurant->slug.'.restaurant-app.test/')
        ->assertOk()
        ->assertDontSee('Our menu')
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('tiles.0.label', 'எங்கள் மெனு'),
        );
});

it('serves the staff app its own strings, not the guest app\'s', function (): void {
    $restaurant = Restaurant::factory()->create();

    $this->withUnencryptedCookie(SetLocale::COOKIE, Locale::Tamil->value)
        ->get('http://'.$restaurant->slug.'.restaurant-app.test/staff/login')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('translations.login.heading', 'பணியாளர் உள்நுழைவு')
            // A guest never downloads "Sold out" and staff never download the
            // tile empty state.
            ->missing('translations.home.empty_tiles')
            ->has('translations.item.sold_out'),
        );
});

/*
|--------------------------------------------------------------------------
| A language the app does not have
|--------------------------------------------------------------------------
*/

it('refuses a language the app is not available in', function (): void {
    $restaurant = Restaurant::factory()->create();

    $this->put(
        'http://'.$restaurant->slug.'.restaurant-app.test/preferences/language',
        ['locale' => 'fr'],
    )->assertSessionHasErrors('locale');
});

it('ignores a tampered cookie rather than breaking the page', function (): void {
    [$restaurant, $menu] = seedBilingualMenu();

    // The cookie is unencrypted and therefore visitor-controlled, so a value
    // that is not a language is an ordinary thing to be handed.
    $this->withUnencryptedCookie(SetLocale::COOKIE, 'klingon')
        ->get(menuUrl($restaurant, $menu))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('locale.current', Locale::English->value),
        );
});

/*
|--------------------------------------------------------------------------
| The language files themselves
|--------------------------------------------------------------------------
*/

it('offers a Tamil file for every English one, with matching keys', function (): void {
    foreach (['guest', 'staff'] as $file) {
        $english = require lang_path('en/'.$file.'.php');
        $tamil = require lang_path('ta/'.$file.'.php');

        // A missing key falls back to English at runtime, but a file that has
        // drifted is worth catching here rather than in front of a guest.
        expect(array_keys(dotKeys($tamil)))
            ->toEqualCanonicalizing(array_keys(dotKeys($english)));
    }
});

it('lists exactly the languages the application supports', function (): void {
    expect(Locale::values())->toBe(['en', 'ta'])
        ->and(Locale::default())->toBe(Locale::English)
        ->and(Locale::English->next())->toBe(Locale::Tamil)
        ->and(Locale::Tamil->next())->toBe(Locale::English);

    foreach (Locale::cases() as $locale) {
        expect(is_dir(lang_path($locale->value)))->toBeTrue();
    }
});

/**
 * Flatten a nested translation array to dotted keys.
 *
 * @param  array<string, mixed>  $values
 * @return array<string, string>
 */
function dotKeys(array $values, string $prefix = ''): array
{
    $flat = [];

    foreach ($values as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

        if (is_array($value)) {
            $flat = [...$flat, ...dotKeys($value, $path)];

            continue;
        }

        $flat[$path] = (string) $value;
    }

    return $flat;
}
