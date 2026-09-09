<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where a dish sits when its category has been subdivided.
     *
     * Nullable, and the category stays required alongside it: a dish is always
     * filed under a category, and *may* additionally sit in one of that
     * category's sub-categories. Making the category nullable instead — "one
     * or the other" — was the alternative, and it would have broken every
     * query that reaches a menu through its categories (Menu::menuItems() is a
     * HasManyThrough over exactly that path) for no gain a reader would see.
     *
     * The composite foreign key is the whole point of the pair. It references
     * menu_sub_categories (id, menu_category_id), so a dish can only name a
     * sub-category that belongs to the very category it is filed under —
     * enforced by the database rather than by remembering to check. When the
     * sub-category is null the constraint is not evaluated at all, which is how
     * a dish filed straight under its category passes it.
     */
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('menu_sub_category_id')->nullable()->after('menu_category_id');

            $table->index(['menu_sub_category_id', 'position']);

            // Deleting a sub-category takes its dishes with it, matching what
            // deleting a category already does. The panel warns before either.
            //
            // Cascading on *update* is what makes moving a sub-category between
            // the categories of a menu possible at all. A dish stores both
            // halves of the pair, so rewriting only the sub-category's own
            // menu_category_id would leave every dish under it pointing at a
            // pair that no longer exists — and rewriting the dishes first would
            // do the same in the other direction. There is no order of two
            // statements that is legal at every step. With this, moving the
            // sub-category carries its dishes across inside the one statement,
            // and dishes with no sub-category are untouched because a null side
            // means the constraint is not evaluated for them.
            $table->foreign(['menu_sub_category_id', 'menu_category_id'])
                ->references(['id', 'menu_category_id'])
                ->on('menu_sub_categories')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });

        $this->restoreNameIndex();
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropForeign(['menu_sub_category_id', 'menu_category_id']);
            $table->dropIndex(['menu_sub_category_id', 'position']);
            $table->dropColumn('menu_sub_category_id');
        });

        $this->restoreNameIndex();
    }

    /**
     * Put the dish-name unique index back after SQLite has rebuilt the table.
     *
     * SQLite cannot add a foreign key with ALTER TABLE, so Laravel recreates
     * the whole table and copies the indexes across — reading them from
     * `pragma index_info`, which reports columns and **not** expressions. The
     * unique index on `(menu_category_id, (name ->> 'en'))` therefore comes out
     * the other side as a unique on `menu_category_id` alone, which would let
     * one category hold exactly one dish. Silently, and only on SQLite, which
     * is what the test suite runs on.
     *
     * So it is dropped and rebuilt from its real definition here. On Postgres
     * nothing was rebuilt and this is simply the same index again, which keeps
     * one statement correct on both engines rather than branching on the
     * driver. Any future migration that alters menu_items in a way SQLite has
     * to rebuild for — a foreign key, a dropped column, a changed type — has to
     * do the same. See .ai/rules/migrations.md.
     */
    private function restoreNameIndex(): void
    {
        DB::statement('DROP INDEX IF EXISTS menu_items_menu_category_id_name_en_unique');
        DB::statement("CREATE UNIQUE INDEX menu_items_menu_category_id_name_en_unique ON menu_items (menu_category_id, (name ->> 'en'))");
    }
};
