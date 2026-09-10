<?php

use App\Enums\Locale;
use App\Enums\Role as RoleEnum;
use App\Filament\Admin\Resources\Menus\Pages\ArrangeMenu;
use App\Filament\Schemas\TranslatedFields;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/*
|--------------------------------------------------------------------------
| One box, one switcher
|--------------------------------------------------------------------------
|
| A translated field has an input per language underneath, but only the
| switched-to one is on screen. What matters is that the ones off screen still
| reach the save, and that the rules built on English still fire while Tamil is
| the language being looked at.
|
*/

it('shows only the language the form is switched to', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['tenant_id' => $restaurant->getKey()]);
    enterRestaurantPanel($restaurant, RoleEnum::Admin);

    // The schema argument is omitted on purpose: the helpers resolve the
    // mounted action's own schema when it is left off.
    Livewire::test(ArrangeMenu::class, ['record' => $menu->getKey()])
        ->mountAction(TestAction::make('createCategory')->table())
        ->assertSchemaComponentStateSet(TranslatedFields::LOCALE_KEY, Locale::default()->value)
        ->assertSchemaComponentVisible('name.'.Locale::English->value)
        ->assertSchemaComponentHidden('name.'.Locale::Tamil->value);
});

it('shows the other language once the switcher is moved', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['tenant_id' => $restaurant->getKey()]);
    enterRestaurantPanel($restaurant, RoleEnum::Admin);

    Livewire::test(ArrangeMenu::class, ['record' => $menu->getKey()])
        ->mountAction(TestAction::make('createCategory')->table())
        ->set('mountedActions.0.data.'.TranslatedFields::LOCALE_KEY, Locale::Tamil->value)
        ->assertSchemaComponentHidden('name.'.Locale::English->value)
        ->assertSchemaComponentVisible('name.'.Locale::Tamil->value);
});

it('keeps the language that is off screen when the form is saved', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['tenant_id' => $restaurant->getKey()]);
    enterRestaurantPanel($restaurant, RoleEnum::Admin);

    // The whole point of dehydratedWhenHidden(): typing the Tamil and saving
    // while English is off screen must not blank the English.
    Livewire::test(ArrangeMenu::class, ['record' => $menu->getKey()])
        ->callAction(TestAction::make('createCategory')->table(), [
            'name' => [Locale::English->value => 'Starters', Locale::Tamil->value => 'தொடக்கங்கள்'],
            TranslatedFields::LOCALE_KEY => Locale::Tamil->value,
            'is_active' => true,
        ])
        ->assertHasNoActionErrors();

    $category = MenuCategory::query()->withoutGlobalScopes()->sole();

    expect($category->getTranslations('name'))->toBe([
        Locale::English->value => 'Starters',
        Locale::Tamil->value => 'தொடக்கங்கள்',
    ]);
});

it('still insists on English while Tamil is the language on screen', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['tenant_id' => $restaurant->getKey()]);
    enterRestaurantPanel($restaurant, RoleEnum::Admin);

    // The required rule lives on the English input, which is hidden here — so
    // it rides on every language's input and reads English out of the state.
    Livewire::test(ArrangeMenu::class, ['record' => $menu->getKey()])
        ->callAction(TestAction::make('createCategory')->table(), [
            'name' => [Locale::Tamil->value => 'தொடக்கங்கள்'],
            TranslatedFields::LOCALE_KEY => Locale::Tamil->value,
            'is_active' => true,
        ])
        ->assertHasActionErrors(['name.'.Locale::Tamil->value]);

    expect(MenuCategory::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('still refuses a duplicate English name while Tamil is on screen', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['tenant_id' => $restaurant->getKey()]);
    MenuCategory::factory()->inMenu($menu)->create(['name' => [Locale::English->value => 'Starters']]);

    enterRestaurantPanel($restaurant, RoleEnum::Admin);

    // Uniqueness is built on the English name in the database, so a save that
    // passed validation here would fail at the index instead.
    Livewire::test(ArrangeMenu::class, ['record' => $menu->getKey()])
        ->callAction(TestAction::make('createCategory')->table(), [
            'name' => [Locale::English->value => 'Starters', Locale::Tamil->value => 'வேறு'],
            TranslatedFields::LOCALE_KEY => Locale::Tamil->value,
            'is_active' => true,
        ])
        ->assertHasActionErrors(['name.'.Locale::Tamil->value]);

    expect(MenuCategory::query()->withoutGlobalScopes()->count())->toBe(1);
});

it('fills the switcher form with every language when editing', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['tenant_id' => $restaurant->getKey()]);
    $category = MenuCategory::factory()->inMenu($menu)->create([
        'name' => [Locale::English->value => 'Starters', Locale::Tamil->value => 'தொடக்கங்கள்'],
    ]);

    enterRestaurantPanel($restaurant, RoleEnum::Admin);

    Livewire::test(ArrangeMenu::class, ['record' => $menu->getKey()])
        ->mountAction(TestAction::make('rename')->table('category-'.$category->getKey()))
        ->assertActionDataSet([
            'name' => [Locale::English->value => 'Starters', Locale::Tamil->value => 'தொடக்கங்கள்'],
        ]);
});
