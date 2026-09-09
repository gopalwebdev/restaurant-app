<?php

use App\Enums\Locale;
use App\Models\HomeTile;
use App\Models\Menu;
use App\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function homeUrl(Restaurant $restaurant): string
{
    return 'http://'.$restaurant->slug.'.restaurant-app.test/';
}

function tileUrl(Restaurant $restaurant, HomeTile $tile, string $suffix = ''): string
{
    return 'http://'.$restaurant->slug.'.restaurant-app.test/tiles/'.$tile->getKey().$suffix;
}

/*
|--------------------------------------------------------------------------
| The tiles a guest lands on
|--------------------------------------------------------------------------
*/

it('shows the tiles in the order the restaurant arranged them', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['restaurant_id' => $restaurant->getKey()]);

    $second = HomeTile::factory()->openingMenu($menu)->create([
        'label' => [Locale::English->value => 'Drinks'],
        'position' => 2,
    ]);
    $first = HomeTile::factory()->openingMenu($menu)->create([
        'label' => [Locale::English->value => 'Food'],
        'position' => 1,
    ]);

    $this->get(homeUrl($restaurant))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('home')
            ->has('tiles', 2)
            ->where('tiles.0.id', $first->getKey())
            ->where('tiles.0.label', 'Food')
            ->where('tiles.1.id', $second->getKey()),
        );
});

it('leaves a hidden tile off the home screen', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['restaurant_id' => $restaurant->getKey()]);

    HomeTile::factory()->openingMenu($menu)->create(['label' => [Locale::English->value => 'Showing']]);
    HomeTile::factory()->openingMenu($menu)->hidden()->create(['label' => [Locale::English->value => 'Hidden']]);

    $this->get(homeUrl($restaurant))
        ->assertOk()
        ->assertSee('Showing')
        ->assertDontSee('Hidden');
});

it('drops a tile whose menu has been taken down', function (): void {
    $restaurant = Restaurant::factory()->create();
    $hiddenMenu = Menu::factory()->hidden()->create(['restaurant_id' => $restaurant->getKey()]);

    // The row is still there and the foreign key is satisfied, so nothing is
    // broken — but tapping it would open a menu the restaurant took down.
    HomeTile::factory()->openingMenu($hiddenMenu)->create();

    $this->get(homeUrl($restaurant))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->has('tiles', 0));
});

it('sends a menu tile to that menu and a PDF tile to its own page', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['restaurant_id' => $restaurant->getKey()]);

    $menuTile = HomeTile::factory()->openingMenu($menu)->create(['position' => 0]);
    $pdfTile = HomeTile::factory()->showingPdf()->create([
        'restaurant_id' => $restaurant->getKey(),
        'position' => 1,
    ]);

    $this->get(homeUrl($restaurant))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('tiles.0.id', $menuTile->getKey())
            ->where('tiles.0.href', route('guest.menus.show', ['restaurant' => $restaurant->slug, 'menu' => $menu->getKey()]))
            ->where('tiles.1.id', $pdfTile->getKey())
            ->where('tiles.1.href', route('guest.tiles.show', ['restaurant' => $restaurant->slug, 'tile' => $pdfTile->getKey()])),
        );
});

it('sends no image url for a tile with no picture yet', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['restaurant_id' => $restaurant->getKey()]);

    HomeTile::factory()->openingMenu($menu)->withoutImage()->create();

    // A tile without a picture is not broken: the guest app draws its label on
    // the brand colour instead.
    $this->get(homeUrl($restaurant))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('tiles.0.imageUrl', null));
});

it('shows only this restaurant\'s tiles', function (): void {
    $mine = Restaurant::factory()->create();
    $theirs = Restaurant::factory()->create();

    HomeTile::factory()
        ->openingMenu(Menu::factory()->create(['restaurant_id' => $mine->getKey()]))
        ->create(['label' => [Locale::English->value => 'Mine']]);

    HomeTile::factory()
        ->openingMenu(Menu::factory()->create(['restaurant_id' => $theirs->getKey()]))
        ->create(['label' => [Locale::English->value => 'Theirs']]);

    $this->get(homeUrl($mine))
        ->assertOk()
        ->assertSee('Mine')
        ->assertDontSee('Theirs');
});

/*
|--------------------------------------------------------------------------
| The PDF behind a tile
|--------------------------------------------------------------------------
*/

it('shows a PDF tile inside the app, with the file behind its own route', function (): void {
    $restaurant = Restaurant::factory()->create();
    $tile = HomeTile::factory()->showingPdf()->create([
        'restaurant_id' => $restaurant->getKey(),
        'label' => [Locale::English->value => 'Wine list'],
    ]);

    // Embedded rather than opened in the browser's own viewer, so the app keeps
    // its header and the guest has a back arrow out of it.
    $this->get(tileUrl($restaurant, $tile))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('document')
            ->where('title', 'Wine list')
            ->where('documentUrl', route('guest.tiles.document.show', [
                'restaurant' => $restaurant->slug,
                'tile' => $tile->getKey(),
            ])),
        );
});

it('serves a tile\'s PDF inline off the private disk', function (): void {
    Storage::fake('local');

    $restaurant = Restaurant::factory()->create();
    $path = Storage::disk('local')->putFile(
        'home-tiles/'.$restaurant->getKey(),
        UploadedFile::fake()->create('wine.pdf', 8, 'application/pdf'),
    );

    $tile = HomeTile::factory()->showingPdf($path)->create(['restaurant_id' => $restaurant->getKey()]);

    $this->get(tileUrl($restaurant, $tile, '/document'))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'inline');
});

it('serves a tile\'s picture off the private disk', function (): void {
    Storage::fake('local');

    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['restaurant_id' => $restaurant->getKey()]);
    $path = Storage::disk('local')->putFile(
        'home-tiles/'.$restaurant->getKey(),
        UploadedFile::fake()->image('tile.jpg'),
    );

    $tile = HomeTile::factory()->openingMenu($menu)->create(['image_path' => $path]);

    $this->get(tileUrl($restaurant, $tile, '/image'))->assertOk();
});

it('404s a file whose upload has gone missing', function (): void {
    Storage::fake('local');

    $restaurant = Restaurant::factory()->create();
    $tile = HomeTile::factory()->showingPdf('home-tiles/1/vanished.pdf')->create([
        'restaurant_id' => $restaurant->getKey(),
    ]);

    $this->get(tileUrl($restaurant, $tile, '/document'))->assertNotFound();
});

it('refuses a tile that is not this restaurant\'s', function (): void {
    Storage::fake('local');

    $mine = Restaurant::factory()->create();
    $theirs = Restaurant::factory()->create();

    $path = Storage::disk('local')->putFile(
        'home-tiles/'.$theirs->getKey(),
        UploadedFile::fake()->create('secret.pdf', 8, 'application/pdf'),
    );

    $theirTile = HomeTile::factory()->showingPdf($path)->create(['restaurant_id' => $theirs->getKey()]);

    // The restaurant arrives in the domain rather than the path, so scoped
    // bindings do not cover it: editing the id in the URL would otherwise read
    // another restaurant's files off the same disk.
    $this->get(tileUrl($mine, $theirTile))->assertNotFound();
    $this->get(tileUrl($mine, $theirTile, '/document'))->assertNotFound();
    $this->get(tileUrl($mine, $theirTile, '/image'))->assertNotFound();
});

it('refuses a hidden tile\'s page and files', function (): void {
    $restaurant = Restaurant::factory()->create();
    $tile = HomeTile::factory()->showingPdf()->hidden()->create(['restaurant_id' => $restaurant->getKey()]);

    $this->get(tileUrl($restaurant, $tile))->assertNotFound();
    $this->get(tileUrl($restaurant, $tile, '/document'))->assertNotFound();
});

it('refuses a menu tile\'s document page, which it has none of', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['restaurant_id' => $restaurant->getKey()]);
    $tile = HomeTile::factory()->openingMenu($menu)->create();

    $this->get(tileUrl($restaurant, $tile))->assertNotFound();
});

it('takes a restaurant\'s whole storefront offline when it is switched off', function (): void {
    $restaurant = Restaurant::factory()->create(['is_active' => false]);
    $tile = HomeTile::factory()->showingPdf()->create(['restaurant_id' => $restaurant->getKey()]);

    $this->get(homeUrl($restaurant))->assertNotFound();
    $this->get(tileUrl($restaurant, $tile))->assertNotFound();
    $this->get(tileUrl($restaurant, $tile, '/document'))->assertNotFound();
});
