<?php

namespace Database\Seeders;

use App\Enums\CountryCallingCode;
use App\Enums\Currency;
use App\Enums\FoodType;
use App\Enums\HomeTileAction;
use App\Enums\HomeTileShape;
use App\Enums\Locale;
use App\Enums\Role;
use App\Models\HomeTile;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemAddition;
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
            'name' => ['en' => 'Biryani', 'ta' => 'பிரியாணி'],
            'items' => [
                [
                    'name' => ['en' => 'Hyderabadi Chicken Biryani', 'ta' => 'ஹைதராபாதி சிக்கன் பிரியாணி'],
                    'price_minor_units' => 38000,
                    'food_type' => FoodType::NonVegetarian,
                    'additions' => [
                        ['name' => ['en' => 'Extra raita', 'ta' => 'கூடுதல் ராய்தா'], 'price_minor_units' => 3000],
                        ['name' => ['en' => 'Boiled egg', 'ta' => 'வேகவைத்த முட்டை'], 'price_minor_units' => 2500],
                    ],
                ],
                [
                    'name' => ['en' => 'Vegetable Dum Biryani', 'ta' => 'வெஜிடபிள் தம் பிரியாணி'],
                    'price_minor_units' => 30000,
                    'food_type' => FoodType::Vegetarian,
                ],
                [
                    'name' => ['en' => 'Egg Biryani', 'ta' => 'முட்டை பிரியாணி'],
                    'price_minor_units' => 27500,
                    'food_type' => FoodType::Egg,
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

        foreach (self::MENU as $position => $section) {
            $category = $this->firstOrCreateByEnglishName(
                MenuCategory::query()->where('menu_id', $menu->getKey()),
                $section['name'],
                fn (): MenuCategory => new MenuCategory(['position' => $position, 'is_active' => true]),
                ['tenant_id' => $restaurant->getKey(), 'menu_id' => $menu->getKey()],
            );

            foreach ($section['items'] as $itemPosition => $item) {
                $dish = $this->firstOrCreateByEnglishName(
                    MenuItem::query()->where('menu_category_id', $category->getKey()),
                    $item['name'],
                    fn (): MenuItem => new MenuItem([
                        'price_minor_units' => $item['price_minor_units'],
                        'food_type' => $item['food_type'],
                        'is_available' => true,
                        'position' => $itemPosition,
                    ]),
                    ['tenant_id' => $restaurant->getKey(), 'menu_category_id' => $category->getKey()],
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
        }

        $this->seedHomeScreen($restaurant, $menu);
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
        $this->firstOrCreateByEnglishName(
            HomeTile::query()->where('tenant_id', $restaurant->getKey()),
            ['en' => 'Menu', 'ta' => 'மெனு'],
            fn (): HomeTile => new HomeTile([
                'shape' => HomeTileShape::Rectangle,
                'action' => HomeTileAction::Menu,
                'position' => 0,
                'is_active' => true,
            ]),
            ['tenant_id' => $restaurant->getKey(), 'menu_id' => $menu->getKey()],
            column: 'label',
        );
    }

    /**
     * Find a record by the English half of a translated column, or make it.
     *
     * @template TModel of Menu|MenuCategory|MenuItem|MenuItemAddition|HomeTile
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
