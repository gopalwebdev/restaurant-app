<?php

namespace App\Actions\Menus;

use App\Enums\Locale;
use App\Models\Menu;
use App\Models\MenuCategory;
use LogicException;

/**
 * Move a category, and everything in it, onto another of the same
 * restaurant's menus.
 *
 * A restaurant that splits one card into a lunch and a dinner menu wants to
 * carry a whole category across rather than retype it. Its sub-categories and
 * its dishes come along without being touched: both hang off the category, and
 * neither carries a menu of its own. That is also why moving a category is the
 * only way a sub-category ever changes menus — see MoveSubCategoryToCategory,
 * which is deliberately limited to the categories of one menu.
 *
 * Two guards, both backstops: MenuCategoriesTable states the same rules as
 * validation, so the panel never reaches these. The target menu has to belong
 * to the same restaurant — the composite foreign key would refuse otherwise,
 * but only as a 500 — and the name has to be free on the target, because
 * uniqueness is per menu and the expression index would reject the update
 * after the form had already passed.
 */
class MoveCategoryToMenu
{
    /**
     * @throws LogicException when the target menu belongs to another
     *                        restaurant, or already has this name on it
     */
    public function __invoke(MenuCategory $category, Menu $target): void
    {
        if ($category->menu_id === $target->getKey()) {
            return;
        }

        throw_if(
            $target->tenant_id !== $category->tenant_id,
            LogicException::class,
            'A category may only move to a menu of its own restaurant.',
        );

        $taken = MenuCategory::query()
            ->withoutGlobalScopes()
            ->where('menu_id', $target->getKey())
            ->where('name->'.Locale::default()->value, $category->getTranslation('name', Locale::default()->value))
            ->exists();

        throw_if(
            $taken,
            LogicException::class,
            'That menu already has a category with this name.',
        );

        // The dishes under it, and any sub-categories, carry
        // menu_category_id rather than menu_id, so they follow without being
        // rewritten.
        $category->update(['menu_id' => $target->getKey()]);

        // Featuring is per menu — the featured row is "what *this* menu leads
        // with" — so a dish that has just left a menu cannot still be at the
        // top of it, and must not silently appear at the top of the one it
        // arrived on. It stays on the menu under this category; only the
        // leading-with stops. MoveItemToSection applies the same rule to a
        // single dish.
        $category->menuItems()
            ->where('is_featured', true)
            ->update(['is_featured' => false, 'featured_position' => 0]);
    }
}
