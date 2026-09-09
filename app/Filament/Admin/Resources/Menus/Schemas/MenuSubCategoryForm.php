<?php

namespace App\Filament\Admin\Resources\Menus\Schemas;

use App\Filament\Schemas\TranslatedFields;
use App\Models\MenuCategory;
use App\Models\MenuSubCategory;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Naming a sub-category and saying which category it subdivides.
 *
 * Unlike MenuCategoryForm this *does* carry its parent select, because the page
 * it lives on is the menu rather than the category — the category is a real
 * choice here, and only the categories of this menu are ever offered. Moving
 * one afterwards is its own action, for the same reason a category's move is.
 */
class MenuSubCategoryForm
{
    /**
     * The columns this form edits in more than one language.
     *
     * @var list<string>
     */
    public const array TRANSLATED = ['name'];

    public static function configure(Schema $schema, ?int $menuId = null): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TranslatedFields::localeSwitcher(),

                Section::make(__('panel.sub_categories.section'))
                    ->description(__('panel.shared.both_languages'))
                    ->icon(Heroicon::OutlinedSquares2x2)
                    ->schema([
                        // Only this menu's categories, and the composite
                        // foreign key refuses anything else even if the
                        // submitted id is tampered with.
                        Select::make('menu_category_id')
                            ->label(__('panel.categories.section'))
                            ->options(fn (): array => self::categoryOptions($menuId))
                            ->default(fn (): ?int => self::onlyCategoryKey($menuId))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->prefixIcon(Heroicon::OutlinedRectangleStack)
                            ->helperText(__('panel.sub_categories.category_help')),

                        ...TranslatedFields::text(
                            'name',
                            __('panel.shared.name'),
                            maxLength: 64,
                            // Unique within the category, matching the
                            // expression index: two categories of one menu may
                            // each have a "Chicken".
                            uniqueWithin: fn (Get $get): Builder => MenuSubCategory::query()
                                ->where('menu_category_id', $get('menu_category_id')),
                            uniqueMessage: __('panel.sub_categories.unique'),
                        ),
                    ])
                    ->columns(2),

                Section::make(__('panel.categories.on_the_menu'))
                    ->icon(Heroicon::OutlinedEye)
                    ->schema([
                        Toggle::make('is_active')
                            ->label(__('panel.sub_categories.is_active'))
                            ->default(true)
                            ->inline(false)
                            ->helperText(__('panel.sub_categories.is_active_help')),
                    ]),
            ]);
    }

    /**
     * Put every language back into the form when a sub-category is edited.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fillTranslations(array $data, MenuSubCategory $record): array
    {
        return $record->fillTranslationsInto($data, ...self::TRANSLATED);
    }

    /**
     * The categories of one menu, in the order the restaurant arranged them.
     *
     * @return array<int, string>
     */
    public static function categoryOptions(?int $menuId): array
    {
        if ($menuId === null) {
            return [];
        }

        return MenuCategory::query()
            ->where('menu_id', $menuId)
            ->inMenuOrder()
            ->get()
            ->mapWithKeys(fn (MenuCategory $category): array => [$category->getKey() => $category->name])
            ->all();
    }

    /**
     * Every category this restaurant has, labelled with its menu.
     *
     * For the places that are not inside one menu — the dish form, which may
     * file a dish anywhere. Two menus may each have a "Starters", so the menu
     * has to be part of the label or the select offers the same word twice.
     *
     * @return array<int, string>
     */
    public static function categoryOptionsForRestaurant(?int $tenantId): array
    {
        return MenuCategory::query()
            ->where('tenant_id', $tenantId)
            ->with('menu')
            ->inMenuOrder()
            ->get()
            // menu_id is not nullable and cascades, so a category always has a
            // menu — there is nothing to fall back to here.
            ->mapWithKeys(fn (MenuCategory $category): array => [
                $category->getKey() => sprintf('%s · %s', $category->menu->name, $category->name),
            ])
            ->all();
    }

    /**
     * The sub-categories of one category, or none when it has not been
     * subdivided.
     *
     * @return array<int, string>
     */
    public static function subCategoryOptions(mixed $categoryId): array
    {
        if (blank($categoryId)) {
            return [];
        }

        return MenuSubCategory::query()
            ->where('menu_category_id', $categoryId)
            ->inMenuOrder()
            ->get()
            ->mapWithKeys(fn (MenuSubCategory $subCategory): array => [
                $subCategory->getKey() => $subCategory->name,
            ])
            ->all();
    }

    /**
     * The category to preselect when the menu has only one to choose.
     */
    private static function onlyCategoryKey(?int $menuId): ?int
    {
        $categories = self::categoryOptions($menuId);

        return count($categories) === 1 ? array_key_first($categories) : null;
    }
}
