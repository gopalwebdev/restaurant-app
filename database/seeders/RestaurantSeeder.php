<?php

namespace Database\Seeders;

use App\Enums\CountryCallingCode;
use App\Enums\Currency;
use App\Enums\FoodType;
use App\Enums\HomeRowLayout;
use App\Enums\HomeTileAction;
use App\Enums\ItemAvailability;
use App\Enums\Locale;
use App\Enums\Role;
use App\Models\HomeRow;
use App\Models\HomeTile;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuCombo;
use App\Models\MenuComboItem;
use App\Models\MenuItem;
use App\Models\MenuItemAddition;
use App\Models\MenuSubCategory;
use App\Models\Restaurant;
use App\Models\User;
use Closure;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

/**
 * One restaurant, its settings, and the one administrator who runs it.
 *
 * The admin address uses plus-addressing so every restaurant gets a distinct
 * account while the sign-in codes all land in the same real inbox.
 */
class RestaurantSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * The restaurants to seed, keyed by the slug that becomes their subdomain.
     *
     * @var list<array{slug: string, name: string, address: string, pincode: string, email: string, phone: string, admin_name: string, admin_email: string, staff_name: string, staff_email: string}>
     */
    public const array RESTAURANTS = [
        [
            'slug' => 'spice',
            'name' => 'Spice Garden',
            'address' => '12 Mount Road, Chennai',
            'pincode' => '600002',
            'email' => 'hello@spicegarden.example.com',
            'phone' => '9876543210',
            'admin_name' => 'Spice Garden Admin',
            'admin_email' => 'gopalwebdev+spice@gmail.com',
            'staff_name' => 'Spice Garden Staff',
            'staff_email' => 'gopalwebdev+spice-staff@gmail.com',
        ],
    ];

    /**
     * The name of the one menu every seeded restaurant gets, in both languages.
     *
     * Seeded copy is bilingual on purpose: it is the only way to see that the
     * language toggle in the guest and staff apps really does anything without
     * typing a Tamil menu out by hand first.
     *
     * @var array<string, string>
     */
    public const array MENU_NAME = ['en' => 'Main Menu', 'ta' => 'முதன்மை மெனு'];

    /**
     * The second menu every seeded restaurant gets.
     *
     * One menu was enough to read a storefront but not enough to work the
     * panel: moving a category to another menu, and filing one under the right
     * card, both need somewhere to move it to.
     *
     * @var array<string, string>
     */
    public const array DRINKS_MENU_NAME = ['en' => 'Drinks', 'ta' => 'பானங்கள்'];

    /**
     * The bundles the main menu leads with, beside its featured dishes.
     *
     * Priced below what the dishes come to separately, which is the whole
     * point of a combo — the contents are named so a guest can see what they
     * are getting, never to be added up.
     *
     * @var list<array{
     *     name: array<string, string>,
     *     description: array<string, string>,
     *     price_minor_units: int,
     *     compare_at_price_minor_units: int,
     *     contents: list<array{name: array<string, string>, quantity: int}>
     * }>
     */
    public const array COMBOS = [
        [
            'name' => ['en' => 'Biryani Feast', 'ta' => 'பிரியாணி விருந்து'],
            'description' => [
                'en' => 'Chicken biryani, a starter and a bread.',
                'ta' => 'சிக்கன் பிரியாணி, ஒரு தொடக்கம், ஒரு ரொட்டி.',
            ],
            'price_minor_units' => 59900,
            'compare_at_price_minor_units' => 75900,
            'contents' => [
                ['name' => ['en' => 'Hyderabadi Chicken Biryani'], 'quantity' => 1],
                ['name' => ['en' => 'Chicken 65'], 'quantity' => 1],
                ['name' => ['en' => 'Butter Naan'], 'quantity' => 2],
            ],
        ],
    ];

    /**
     * The starter drinks card.
     *
     * @var list<array{
     *     name: array<string, string>,
     *     items: list<array{name: array<string, string>, price_minor_units: int, food_type: FoodType}>
     * }>
     */
    public const array DRINKS = [
        [
            'name' => ['en' => 'Hot drinks', 'ta' => 'சூடான பானங்கள்'],
            'items' => [
                ['name' => ['en' => 'Filter Coffee', 'ta' => 'பில்டர் காபி'], 'price_minor_units' => 4000, 'food_type' => FoodType::Vegetarian],
                ['name' => ['en' => 'Masala Chai', 'ta' => 'மசாலா டீ'], 'price_minor_units' => 3500, 'food_type' => FoodType::Vegetarian],
            ],
        ],
        [
            'name' => ['en' => 'Cold drinks', 'ta' => 'குளிர் பானங்கள்'],
            'items' => [
                ['name' => ['en' => 'Fresh Lime Soda', 'ta' => 'எலுமிச்சை சோடா'], 'price_minor_units' => 6000, 'food_type' => FoodType::Vegetarian],
                ['name' => ['en' => 'Mango Lassi', 'ta' => 'மாம்பழ லஸ்ஸி'], 'price_minor_units' => 9000, 'food_type' => FoodType::Vegetarian],
            ],
        ],
    ];

    /**
     * The starter menu every seeded restaurant gets.
     *
     * Every name is a map of locale to text, exactly as the columns store it.
     * Additions are the extras a dish can be ordered with, priced in the minor
     * unit like everything else, and zero is a real price.
     *
     * @var list<array{
     *     name: array<string, string>,
     *     items: list<array{
     *         name: array<string, string>,
     *         price_minor_units: int,
     *         food_type: FoodType,
     *         additions?: list<array{name: array<string, string>, price_minor_units: int}>
     *     }>
     * }>
     */
    public const array MENU = [
        [
            'name' => ['en' => 'Starters', 'ta' => 'தொடக்கங்கள்'],
            'items' => [
                [
                    'name' => ['en' => 'Paneer Tikka', 'ta' => 'பன்னீர் டிக்கா'],
                    'price_minor_units' => 24950,
                    'food_type' => FoodType::Vegetarian,
                    'additions' => [
                        ['name' => ['en' => 'Extra paneer', 'ta' => 'கூடுதல் பன்னீர்'], 'price_minor_units' => 5000],
                        ['name' => ['en' => 'Less spicy', 'ta' => 'குறைந்த காரம்'], 'price_minor_units' => 0],
                    ],
                ],
                [
                    'name' => ['en' => 'Gobi Manchurian', 'ta' => 'கோபி மஞ்சூரியன்'],
                    'price_minor_units' => 21000,
                    'food_type' => FoodType::Vegetarian,
                ],
                [
                    'name' => ['en' => 'Chicken 65', 'ta' => 'சிக்கன் 65'],
                    'price_minor_units' => 29900,
                    'food_type' => FoodType::NonVegetarian,
                ],
            ],
        ],
        [
            // The one subdivided category, so a seeded restaurant shows what
            // sub-categories are for without every category needing them.
            'name' => ['en' => 'Biryani', 'ta' => 'பிரியாணி'],
            'items' => [
                [
                    'name' => ['en' => 'Egg Biryani', 'ta' => 'முட்டை பிரியாணி'],
                    'price_minor_units' => 27500,
                    'food_type' => FoodType::Egg,
                ],
            ],
            'sub_categories' => [
                [
                    'name' => ['en' => 'Chicken', 'ta' => 'சிக்கன்'],
                    'items' => [
                        [
                            'name' => ['en' => 'Hyderabadi Chicken Biryani', 'ta' => 'ஹைதராபாதி சிக்கன் பிரியாணி'],
                            'price_minor_units' => 38000,
                            // The one dish on offer, so the struck-through
                            // price has somewhere to show.
                            'compare_at_price_minor_units' => 45000,
                            'food_type' => FoodType::NonVegetarian,
                            'additions' => [
                                ['name' => ['en' => 'Extra raita', 'ta' => 'கூடுதல் ராய்தா'], 'price_minor_units' => 3000],
                                ['name' => ['en' => 'Boiled egg', 'ta' => 'வேகவைத்த முட்டை'], 'price_minor_units' => 2500],
                            ],
                        ],
                    ],
                ],
                [
                    'name' => ['en' => 'Vegetable', 'ta' => 'காய்கறி'],
                    'items' => [
                        [
                            'name' => ['en' => 'Vegetable Dum Biryani', 'ta' => 'வெஜிடபிள் தம் பிரியாணி'],
                            'price_minor_units' => 30000,
                            'food_type' => FoodType::Vegetarian,
                        ],
                    ],
                ],
            ],
        ],
        [
            'name' => ['en' => 'Breads', 'ta' => 'ரொட்டிகள்'],
            'items' => [
                [
                    'name' => ['en' => 'Butter Naan', 'ta' => 'பட்டர் நான்'],
                    'price_minor_units' => 8000,
                    'food_type' => FoodType::Vegetarian,
                    'additions' => [
                        ['name' => ['en' => 'Extra butter', 'ta' => 'கூடுதல் வெண்ணெய்'], 'price_minor_units' => 2000],
                    ],
                ],
                [
                    'name' => ['en' => 'Tandoori Roti', 'ta' => 'தந்தூரி ரொட்டி'],
                    'price_minor_units' => 5000,
                    'food_type' => FoodType::Vegetarian,
                ],
            ],
        ],
        [
            'name' => ['en' => 'Desserts', 'ta' => 'இனிப்புகள்'],
            'items' => [
                [
                    'name' => ['en' => 'Gulab Jamun', 'ta' => 'குலாப் ஜாமூன்'],
                    'price_minor_units' => 12000,
                    'food_type' => FoodType::Vegetarian,
                ],
                [
                    'name' => ['en' => 'Rasmalai', 'ta' => 'ரஸ்மலாய்'],
                    'price_minor_units' => 14000,
                    'food_type' => FoodType::Vegetarian,
                ],
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::RESTAURANTS as $definition) {
            $restaurant = Restaurant::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'address' => $definition['address'],
                    'pincode' => $definition['pincode'],
                    'email' => $definition['email'],
                    'phone_country_code' => CountryCallingCode::India,
                    'phone' => $definition['phone'],
                    'is_active' => true,
                ],
            );

            $restaurant->settings()->firstOrCreate([], [
                'contact_email' => "hello@{$definition['slug']}.example.com",
                'contact_phone' => '+91 98765 43210',
                'currency' => Currency::IndianRupee,
                'accepts_orders' => true,
                'opens_at' => '09:00:00',
                'closes_at' => '23:00:00',
            ]);

            // The admin belongs to this restaurant, not the product team: a
            // null tenant_id would file them under "Product team" in the
            // super-admin panel, which they are not.
            $admin = User::query()->firstOrCreate(
                ['email' => $definition['admin_email']],
                ['name' => $definition['admin_name'], 'tenant_id' => $restaurant->getKey(), 'email_verified_at' => now()],
            );

            $admin->syncRoles([Role::Admin->value]);
            $restaurant->users()->syncWithoutDetaching([$admin->getKey()]);

            // Someone to open the staff app with. Plus-addressing means every
            // seeded account's sign-in code lands in the same real inbox.
            $staff = User::query()->firstOrCreate(
                ['email' => $definition['staff_email']],
                ['name' => $definition['staff_name'], 'tenant_id' => $restaurant->getKey(), 'email_verified_at' => now()],
            );

            $staff->syncRoles([Role::Staff->value]);
            $restaurant->users()->syncWithoutDetaching([$staff->getKey()]);

            $this->seedMenu($restaurant);
        }
    }

    /**
     * A small bilingual menu, so a fresh install has something to look at.
     *
     * Prices are in the minor unit, as the column is: 24950 is ₹249.50.
     *
     * Every lookup matches on the English name rather than the whole translated
     * column, because a JSON document only compares equal when every language
     * in it does — which would make this seeder duplicate its own menu the
     * first time a restaurant translated one dish.
     */
    private function seedMenu(Restaurant $restaurant): void
    {
        $menu = $this->firstOrCreateByEnglishName(
            Menu::query()->where('tenant_id', $restaurant->getKey()),
            self::MENU_NAME,
            fn (): Menu => new Menu(['position' => 0, 'is_active' => true]),
            ['tenant_id' => $restaurant->getKey()],
        );

        $this->seedCard($restaurant, $menu, self::MENU);

        $drinks = $this->firstOrCreateByEnglishName(
            Menu::query()->where('tenant_id', $restaurant->getKey()),
            self::DRINKS_MENU_NAME,
            fn (): Menu => new Menu(['position' => 1, 'is_active' => true]),
            ['tenant_id' => $restaurant->getKey()],
        );

        $this->seedCard($restaurant, $drinks, self::DRINKS);

        // After the card, because a combo names dishes that have to exist.
        $this->seedCombos($restaurant, $menu);

        $this->seedHomeScreen($restaurant, $menu);
    }

    /**
     * One menu's categories, their dishes, and each dish's additions.
     *
     * @param  list<array<string, mixed>>  $card
     */
    private function seedCard(Restaurant $restaurant, Menu $menu, array $card): void
    {
        foreach ($card as $position => $section) {
            $category = $this->firstOrCreateByEnglishName(
                MenuCategory::query()->where('menu_id', $menu->getKey()),
                $section['name'],
                fn (): MenuCategory => new MenuCategory(['position' => $position, 'is_active' => true]),
                ['tenant_id' => $restaurant->getKey(), 'menu_id' => $menu->getKey()],
            );

            foreach ($section['items'] as $itemPosition => $item) {
                $this->seedDish($restaurant, $category, $item, $itemPosition);
            }

            foreach ($section['sub_categories'] ?? [] as $subPosition => $subSection) {
                $subCategory = $this->firstOrCreateByEnglishName(
                    MenuSubCategory::query()->where('menu_category_id', $category->getKey()),
                    $subSection['name'],
                    fn (): MenuSubCategory => new MenuSubCategory(['position' => $subPosition, 'is_active' => true]),
                    ['tenant_id' => $restaurant->getKey(), 'menu_category_id' => $category->getKey()],
                );

                foreach ($subSection['items'] as $itemPosition => $item) {
                    $this->seedDish($restaurant, $category, $item, $itemPosition, $subCategory);
                }
            }
        }
    }

    /**
     * One dish, its offer if it has one, and its additions.
     *
     * The sub-category is optional and the category is not, because that is how
     * a dish is filed: always under a category, and *also* under one of its
     * subdivisions when the category has been broken up.
     *
     * @param  array<string, mixed>  $item
     */
    private function seedDish(
        Restaurant $restaurant,
        MenuCategory $category,
        array $item,
        int $position,
        ?MenuSubCategory $subCategory = null,
    ): void {
        $dish = $this->firstOrCreateByEnglishName(
            MenuItem::query()->where('menu_category_id', $category->getKey()),
            $item['name'],
            fn (): MenuItem => new MenuItem([
                'price_minor_units' => $item['price_minor_units'],
                // Null on almost every dish: not on offer. A zero would be a
                // price of nothing.
                'compare_at_price_minor_units' => $item['compare_at_price_minor_units'] ?? null,
                'food_type' => $item['food_type'],
                'availability' => ItemAvailability::Available,
                'position' => $position,
            ]),
            [
                'tenant_id' => $restaurant->getKey(),
                'menu_category_id' => $category->getKey(),
                'menu_sub_category_id' => $subCategory?->getKey(),
            ],
        );

        foreach ($item['additions'] ?? [] as $additionPosition => $addition) {
            $this->firstOrCreateByEnglishName(
                MenuItemAddition::query()->where('menu_item_id', $dish->getKey()),
                $addition['name'],
                fn (): MenuItemAddition => new MenuItemAddition([
                    'price_minor_units' => $addition['price_minor_units'],
                    'is_available' => true,
                    'position' => $additionPosition,
                ]),
                ['tenant_id' => $restaurant->getKey(), 'menu_item_id' => $dish->getKey()],
            );
        }
    }

    /**
     * The bundles one menu leads with, and what is in each.
     *
     * A combo's contents are looked up by the English name of a dish already
     * seeded onto this menu. A name that finds nothing is skipped rather than
     * failing the seed: the combo is still a working combo one line shorter,
     * and a half-seeded database is worse than a slightly smaller one.
     */
    private function seedCombos(Restaurant $restaurant, Menu $menu): void
    {
        foreach (self::COMBOS as $position => $definition) {
            $combo = $this->firstOrCreateByEnglishName(
                MenuCombo::query()->where('menu_id', $menu->getKey()),
                $definition['name'],
                fn (): MenuCombo => new MenuCombo([
                    'description' => $definition['description'],
                    'price_minor_units' => $definition['price_minor_units'],
                    'compare_at_price_minor_units' => $definition['compare_at_price_minor_units'],
                    'availability' => ItemAvailability::Available,
                    'position' => $position,
                ]),
                ['tenant_id' => $restaurant->getKey(), 'menu_id' => $menu->getKey()],
            );

            foreach ($definition['contents'] as $contentPosition => $content) {
                $dish = MenuItem::query()
                    ->where('tenant_id', $restaurant->getKey())
                    ->onMenu($menu->getKey())
                    ->where('name->'.Locale::English->value, $content['name'][Locale::English->value])
                    ->first();

                if (! $dish instanceof MenuItem) {
                    continue;
                }

                MenuComboItem::query()->firstOrCreate(
                    ['menu_combo_id' => $combo->getKey(), 'menu_item_id' => $dish->getKey()],
                    [
                        'tenant_id' => $restaurant->getKey(),
                        'quantity' => $content['quantity'],
                        'position' => $contentPosition,
                    ],
                );
            }
        }
    }

    /**
     * The one tile a freshly seeded restaurant's guests land on.
     *
     * No picture: there is no photography to seed, and a tile without one is a
     * working tile — the guest app draws the label on the brand colour. The
     * restaurant replaces it from the admin panel.
     */
    private function seedHomeScreen(Restaurant $restaurant, Menu $menu): void
    {
        // One banner row holding one tile into the menu: the smallest home
        // screen that actually works, and the shape most restaurants start
        // from before they add a rail of photographs beside it.
        $row = HomeRow::query()->firstOrCreate(
            ['tenant_id' => $restaurant->getKey(), 'position' => 0],
            ['layout' => HomeRowLayout::Banner, 'is_active' => true],
        );

        $this->firstOrCreateByEnglishName(
            HomeTile::query()->where('home_row_id', $row->getKey()),
            ['en' => 'Menu', 'ta' => 'மெனு'],
            fn (): HomeTile => new HomeTile([
                'action' => HomeTileAction::Menu,
                'position' => 0,
                'is_active' => true,
            ]),
            [
                'tenant_id' => $restaurant->getKey(),
                'home_row_id' => $row->getKey(),
                'menu_id' => $menu->getKey(),
            ],
            column: 'label',
        );
    }

    /**
     * Find a record by the English half of a translated column, or make it.
     *
     * @template TModel of Menu|MenuCategory|MenuSubCategory|MenuItem|MenuItemAddition|MenuCombo|HomeTile
     *
     * @param  Builder<TModel>  $query  already narrowed to the right parent
     * @param  array<string, string>  $translations  the name in every language
     * @param  Closure(): TModel  $make  a new, unsaved record with its own columns set
     * @param  array<string, mixed>  $owner  the keys tying it to its restaurant and parent
     * @return TModel
     */
    private function firstOrCreateByEnglishName(
        Builder $query,
        array $translations,
        Closure $make,
        array $owner,
        string $column = 'name',
    ): Model {
        $english = Locale::default()->value;

        $record = (clone $query)
            ->where($column.'->'.$english, $translations[$english])
            ->first();

        $record ??= $make();

        $record->setTranslations($column, $translations);
        $record->forceFill($owner)->save();

        return $record;
    }
}
