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
 * carry a whole category across rather than retype it. The dishes come along
 * without being touched: they hang off the category, not off the menu.
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

        // The dishes under it carry menu_category_id, not menu_id, so they
        // follow without being rewritten.
        $category->update(['menu_id' => $target->getKey()]);
    }
}
