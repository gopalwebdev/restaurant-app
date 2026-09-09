<?php

namespace App\Actions\Menus;

use App\Enums\Locale;
use App\Models\MenuCategory;
use App\Models\MenuSubCategory;
use LogicException;

/**
 * Move a sub-category, and its dishes, under another category of the same menu.
 *
 * A restaurant that decides "Chicken" belongs under Biryani rather than under
 * Mains carries the whole branch across rather than retyping it.
 *
 * The dishes follow without being touched here, and that is the database's
 * doing rather than an omission: a dish stores both halves of the pair, and the
 * composite foreign key on menu_items is ON UPDATE CASCADE, so rewriting the
 * sub-category's own menu_category_id rewrites theirs in the same statement.
 * Doing it by hand is not merely unnecessary, it is impossible — there is no
 * order of two updates that leaves the pair consistent at every step, which is
 * exactly why the cascade is there. See the migration for the long version.
 *
 * Deliberately within one menu only. A sub-category has no menu of its own; it
 * reaches one through its category, so "move it to another menu" can only mean
 * moving its category, which is what MoveCategoryToMenu already does for the
 * whole branch at once. Offering both would be two ways to do one thing, and
 * the one here would silently split a category across two menus.
 *
 * The guards are backstops thrown as LogicException, exactly as in
 * MoveCategoryToMenu: SubCategoriesRelationManager states the same rules as
 * validation, which is what an admin actually sees. These stop code going
 * around the panel.
 */
class MoveSubCategoryToCategory
{
    /**
     * @throws LogicException when the target belongs to another restaurant or
     *                        another menu, or already has this name under it
     */
    public function __invoke(MenuSubCategory $subCategory, MenuCategory $target): void
    {
        if ($subCategory->menu_category_id === $target->getKey()) {
            return;
        }

        throw_if(
            $target->tenant_id !== $subCategory->tenant_id,
            LogicException::class,
            'A sub-category may only move to a category of its own restaurant.',
        );

        // The composite foreign key would allow this — both categories belong
        // to the restaurant — so this is the only thing standing between a
        // careless call and a sub-category that has quietly changed menus.
        throw_if(
            $target->menu_id !== $subCategory->menuCategory->menu_id,
            LogicException::class,
            'A sub-category may only move between the categories of one menu.',
        );

        $taken = MenuSubCategory::query()
            ->withoutGlobalScopes()
            ->where('menu_category_id', $target->getKey())
            ->where('name->'.Locale::default()->value, $subCategory->getTranslation('name', Locale::default()->value))
            ->exists();

        throw_if(
            $taken,
            LogicException::class,
            'That category already has a sub-category with this name.',
        );

        // One statement. The dishes under it are carried across by the ON
        // UPDATE CASCADE on menu_items, and dishes elsewhere in either category
        // are left alone.
        $subCategory->update(['menu_category_id' => $target->getKey()]);
    }
}
