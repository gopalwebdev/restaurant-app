<?php

namespace App\Actions\Menus;

use App\Enums\Locale;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuSubCategory;
use LogicException;

/**
 * File a dish under a different category, or a different sub-category.
 *
 * One action for both because they are one question — "where on the menu does
 * this dish sit" — and a dish answers it with a pair of columns that have to be
 * written together. Passing a sub-category sets both halves from it; passing
 * only a category clears the sub-category, which is how a dish is lifted out of
 * a subdivision back up to the category itself.
 *
 * A dish may move anywhere in the restaurant, including onto a different menu:
 * it reaches its menu through its category, so moving it to a category of
 * another menu is simply a move, with nothing special about it. That is
 * deliberate — a restaurant splitting a card wants to be able to carry single
 * dishes across, not only whole categories.
 *
 * The dish keeps its additions, which hang off it and do not know where it
 * sits, and it keeps `is_featured` — but featuring is per menu, so a dish
 * carried to another menu is unfeatured on the way, because the row it was
 * being led with belongs to the menu it left.
 *
 * The guards are backstops thrown as LogicException, as in the other menu
 * actions: MenuItemsTable states the same rules as validation, which is what an
 * admin sees. These stop code going around the panel.
 */
class MoveItemToSection
{
    /**
     * @throws LogicException when the target belongs to another restaurant, the
     *                        sub-category is not the category's, or the name is
     *                        taken in the target category
     */
    public function __invoke(MenuItem $item, MenuCategory $category, ?MenuSubCategory $subCategory = null): void
    {
        throw_if(
            $category->tenant_id !== $item->tenant_id,
            LogicException::class,
            'A dish may only move to a category of its own restaurant.',
        );

        // The composite foreign key would refuse this too, but as a 500 rather
        // than something a caller can catch and explain.
        throw_if(
            $subCategory instanceof MenuSubCategory && $subCategory->menu_category_id !== $category->getKey(),
            LogicException::class,
            'That sub-category does not belong to the category the dish is being moved to.',
        );

        $isSameSection = $item->menu_category_id === $category->getKey()
            && $item->menu_sub_category_id === $subCategory?->getKey();

        if ($isSameSection) {
            return;
        }

        // Uniqueness is per category and built on the English name, so this
        // only has to be asked when the category itself is changing — moving
        // between two sub-categories of one category cannot introduce a clash.
        if ($item->menu_category_id !== $category->getKey()) {
            $taken = MenuItem::query()
                ->withoutGlobalScopes()
                ->where('menu_category_id', $category->getKey())
                ->where('name->'.Locale::default()->value, $item->getTranslation('name', Locale::default()->value))
                ->exists();

            throw_if(
                $taken,
                LogicException::class,
                'That category already has a dish with this name.',
            );
        }

        // The featured row belongs to a menu, so a dish that has left that menu
        // cannot still be at the top of it — and must not turn up at the top of
        // the one it arrived on without anyone choosing it there. A dish moved
        // within its own menu keeps its place in the row. MoveCategoryToMenu
        // applies the same rule to a whole category at once.
        $staysFeatured = $item->is_featured
            && $item->menuCategory->menu_id === $category->menu_id;

        $item->update([
            'menu_category_id' => $category->getKey(),
            'menu_sub_category_id' => $subCategory?->getKey(),
            'is_featured' => $staysFeatured,
            'featured_position' => $staysFeatured ? $item->featured_position : 0,
        ]);
    }
}
