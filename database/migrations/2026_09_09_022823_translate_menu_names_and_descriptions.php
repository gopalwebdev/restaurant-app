<?php

use App\Enums\Locale;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Turn every name a guest reads into a translated column.
     *
     * A translated column holds one key per App\Enums\Locale case, and English
     * is always present because the admin forms require it. Everything already
     * stored is English, so it is wrapped as {"en": "..."} on the way through.
     *
     * The order below matters and is not arbitrary: the values are encoded
     * while the column is still text, because Postgres will not cast `Starters`
     * to json; the type change comes second; and the expression indexes come
     * last, because changing a column's type rebuilds the table on SQLite and
     * an index built beforehand would not survive it.
     */
    public function up(): void
    {
        $this->translateMenuCategoryNames();
        $this->translateMenuItemNamesAndDescriptions();
    }

    /**
     * The same steps in reverse, and in the mirrored order.
     *
     * The type goes back to text before the values are unwrapped, because a
     * bare `Starters` is not valid JSON and Postgres would refuse to store it
     * in a json column.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX menu_categories_menu_id_name_en_unique');
        DB::statement('DROP INDEX menu_items_menu_category_id_name_en_unique');

        Schema::table('menu_categories', function (Blueprint $table): void {
            $table->string('name')->using('name::text')->change();
        });

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->string('name')->using('name::text')->change();
            $table->text('description')->nullable()->using('description::text')->change();
        });

        $this->untranslate('menu_categories', ['name']);
        $this->untranslate('menu_items', ['name', 'description']);

        Schema::table('menu_categories', function (Blueprint $table): void {
            $table->unique(['restaurant_id', 'name']);
        });

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropUnique(['id', 'restaurant_id']);
            $table->unique(['restaurant_id', 'name']);
        });
    }

    /**
     * A section's name, and a uniqueness rule that follows the new hierarchy.
     *
     * Uniqueness moves from the restaurant to the menu: now that a restaurant
     * can serve a lunch card and a dinner card, both are allowed a "Starters".
     */
    private function translateMenuCategoryNames(): void
    {
        Schema::table('menu_categories', function (Blueprint $table): void {
            $table->dropUnique(['restaurant_id', 'name']);
        });

        $this->wrapAsEnglish('menu_categories', ['name']);

        Schema::table('menu_categories', function (Blueprint $table): void {
            $table->json('name')->using('name::json')->change();
        });

        DB::statement("CREATE UNIQUE INDEX menu_categories_menu_id_name_en_unique ON menu_categories (menu_id, (name ->> 'en'))");
    }

    /**
     * A dish's name and description, and the key its additions hang off.
     *
     * menu_items gains a unique (id, restaurant_id) it never had: it is the
     * target of the composite foreign key on menu_item_additions, the same
     * shape menu_categories already provides for menu_items.
     */
    private function translateMenuItemNamesAndDescriptions(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropUnique(['restaurant_id', 'name']);
        });

        $this->wrapAsEnglish('menu_items', ['name', 'description']);

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->json('name')->using('name::json')->change();
            $table->json('description')->nullable()->using('description::json')->change();
        });

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->unique(['id', 'restaurant_id']);
        });

        DB::statement("CREATE UNIQUE INDEX menu_items_menu_category_id_name_en_unique ON menu_items (menu_category_id, (name ->> 'en'))");
    }

    /**
     * Rewrite plain text values as a translation document holding English.
     *
     * @param  list<string>  $columns
     */
    private function wrapAsEnglish(string $table, array $columns): void
    {
        $english = Locale::default()->value;

        DB::table($table)->orderBy('id')->each(function (object $row) use ($table, $columns, $english): void {
            $values = [];

            foreach ($columns as $column) {
                $values[$column] = $row->{$column} === null
                    ? null
                    : json_encode([$english => $row->{$column}], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            }

            DB::table($table)->where('id', $row->id)->update($values);
        });
    }

    /**
     * Take the English value back out, for a rollback.
     *
     * Every other language is dropped, which is what rolling back a
     * translation feature means.
     *
     * @param  list<string>  $columns
     */
    private function untranslate(string $table, array $columns): void
    {
        $english = Locale::default()->value;

        DB::table($table)->orderBy('id')->each(function (object $row) use ($table, $columns, $english): void {
            $values = [];

            foreach ($columns as $column) {
                $decoded = $row->{$column} === null ? null : json_decode((string) $row->{$column}, true);
                $values[$column] = is_array($decoded) ? ($decoded[$english] ?? null) : $row->{$column};
            }

            DB::table($table)->where('id', $row->id)->update($values);
        });
    }
};
