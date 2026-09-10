<?php

use App\Enums\Locale;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Turn a section's name into a translated column.
     *
     * A translated column holds one key per App\Enums\Locale case, and English
     * is always present because the admin forms require it. Everything already
     * stored is English, so it is wrapped as {"en": "..."} on the way through.
     *
     * The order below matters and is not arbitrary: the values are encoded
     * while the column is still text, because Postgres will not cast `Starters`
     * to json; the type change comes second; and the expression index comes
     * last, because it indexes the json the type change produces.
     */
    public function up(): void
    {
        // Uniqueness moves from the restaurant to the menu: now that a
        // restaurant can serve a lunch card and a dinner card, both are
        // allowed a "Starters".
        Schema::table('menu_categories', function (Blueprint $table): void {
            $table->dropUnique(['restaurant_id', 'name']);
        });

        $this->rewriteNames(fn (?string $value): ?string => $this->asEnglish($value));

        Schema::table('menu_categories', function (Blueprint $table): void {
            $table->json('name')->using('name::json')->change();
        });

        DB::statement("CREATE UNIQUE INDEX menu_categories_menu_id_name_en_unique ON menu_categories (menu_id, (name ->> 'en'))");
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

        Schema::table('menu_categories', function (Blueprint $table): void {
            $table->string('name')->using('name::text')->change();
        });

        $this->rewriteNames(fn (?string $value): ?string => $this->fromEnglish($value));

        Schema::table('menu_categories', function (Blueprint $table): void {
            $table->unique(['restaurant_id', 'name']);
        });
    }

    /**
     * Rewrite every row's name through the given transform.
     *
     * Walked in chunks rather than loaded at once, so a restaurant with a long
     * menu costs the same memory as one with a short one.
     *
     * @param  Closure(string|null): (string|null)  $transform
     */
    private function rewriteNames(Closure $transform): void
    {
        DB::table('menu_categories')
            ->select(['id', 'name'])
            ->orderBy('id')
            ->chunkById(200, function (object|array $rows) use ($transform): void {
                foreach ($rows as $row) {
                    DB::table('menu_categories')
                        ->where('id', $row->id)
                        ->update(['name' => $transform($row->name)]);
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
