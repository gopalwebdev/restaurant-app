<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A category may sit under another: Biryani → Chicken, Mutton, Vegetable.
     *
     * One table for both levels rather than a `menu_sub_categories` table of its
     * own. The two-table shape was tried first and this replaced it, because a
     * dish then points at **one** category — top level or nested — instead of
     * carrying a (category, sub-category) pair that had to be kept consistent.
     * The composite key and the ON UPDATE CASCADE that pair needed are simply
     * gone: there is nothing left to disagree.
     *
     * It also makes the two moves an admin actually does trivial. Moving a
     * sub-category under a different category is one `parent_id` write, and the
     * dishes follow because they were never pointing at the parent.
     *
     * Depth is capped at two, and that is a product decision rather than a
     * limitation: a menu is read as sections and subdivisions, and arbitrary
     * nesting would mean cycle checks and an ordering story nobody has asked
     * for. MenuCategory::booted() refuses a parent that is itself nested, which
     * is the one rule a foreign key cannot express.
     *
     * The self foreign key is on the **pair** (parent_id, menu_id), not on
     * parent_id alone. That is what stops a category being nested under one on
     * a different menu, and being ON UPDATE CASCADE is what carries a whole
     * branch across when its top-level category moves menus.
     */
    public function up(): void
    {
        Schema::table('menu_categories', function (Blueprint $table): void {
            $table->unsignedBigInteger('parent_id')->nullable()->after('menu_id');

            // Referenced by the self foreign key below.
            $table->unique(['id', 'menu_id']);

            // How every list on the menu page is read: one menu, one level,
            // in the order the restaurant dragged them into.
            $table->index(['menu_id', 'parent_id', 'position']);

            $table->foreign(['parent_id', 'menu_id'])
                ->references(['id', 'menu_id'])
                ->on('menu_categories')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });

        // The name has to be unique per level, not per menu: "Biryani ›
        // Chicken" and "Starters › Chicken" are different things, and so are a
        // top-level Chicken and a nested one. COALESCE because a unique index
        // treats NULLs as distinct, so without it every top-level category
        // would escape the constraint entirely.
        $this->scopeNameIndexToLevel();
    }

    public function down(): void
    {
        Schema::table('menu_categories', function (Blueprint $table): void {
            $table->dropForeign(['parent_id', 'menu_id']);
            $table->dropIndex(['menu_id', 'parent_id', 'position']);
            $table->dropUnique(['id', 'menu_id']);
            $table->dropColumn('parent_id');
        });

        DB::statement('DROP INDEX IF EXISTS menu_categories_menu_id_name_en_unique');
        DB::statement("CREATE UNIQUE INDEX menu_categories_menu_id_name_en_unique ON menu_categories (menu_id, (name ->> 'en'))");
    }

    /**
     * Rebuild the category-name unique index on the level as well as the menu.
     */
    private function scopeNameIndexToLevel(): void
    {
        DB::statement('DROP INDEX IF EXISTS menu_categories_menu_id_name_en_unique');
        DB::statement("CREATE UNIQUE INDEX menu_categories_menu_id_name_en_unique ON menu_categories (menu_id, COALESCE(parent_id, 0), (name ->> 'en'))");
    }
};
