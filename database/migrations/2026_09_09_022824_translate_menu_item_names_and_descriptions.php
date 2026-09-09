<?php

use App\Enums\Locale;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Turn a dish's name and description into translated columns.
     *
     * The same shape as translate_menu_category_names, and for the same
     * reasons — see the note there on why the encode, the type change and the
     * index have to happen in that order.
     *
     * menu_items also gains a unique (id, restaurant_id) it never had: it is
     * the target of the composite foreign key on menu_item_additions, the same
     * shape menu_categories already provides for menu_items.
     */
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropUnique(['restaurant_id', 'name']);
        });

        $this->rewriteText(fn (?string $value): ?string => $this->asEnglish($value));

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->json('name')->using('name::json')->change();
            $table->json('description')->nullable()->using('description::json')->change();
        });

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->unique(['id', 'restaurant_id']);
        });

        // Unique within the section rather than the restaurant: a lunch and a
        // dinner menu may both list a "Paneer Tikka".
        DB::statement("CREATE UNIQUE INDEX menu_items_menu_category_id_name_en_unique ON menu_items (menu_category_id, (name ->> 'en'))");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX menu_items_menu_category_id_name_en_unique');

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->string('name')->using('name::text')->change();
            $table->text('description')->nullable()->using('description::text')->change();
        });

        $this->rewriteText(fn (?string $value): ?string => $this->fromEnglish($value));

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropUnique(['id', 'restaurant_id']);
            $table->unique(['restaurant_id', 'name']);
        });
    }

    /**
     * Rewrite every row's name and description through the given transform.
     *
     * Walked in chunks rather than loaded at once, so a long menu costs the
     * same memory as a short one.
     *
     * @param  Closure(string|null): (string|null)  $transform
     */
    private function rewriteText(Closure $transform): void
    {
        DB::table('menu_items')
            ->select(['id', 'name', 'description'])
            ->orderBy('id')
            ->chunkById(200, function (object|array $rows) use ($transform): void {
                foreach ($rows as $row) {
                    DB::table('menu_items')->where('id', $row->id)->update([
                        'name' => $transform($row->name),
                        'description' => $transform($row->description),
                    ]);
                }
            });
    }

    /**
     * Wrap a plain value as a translation document holding English.
     */
    private function asEnglish(?string $value): ?string
    {
        return $value === null
            ? null
            : json_encode([Locale::default()->value => $value], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * Take the English value back out, dropping every other language.
     */
    private function fromEnglish(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? ($decoded[Locale::default()->value] ?? null) : $value;
    }
};
