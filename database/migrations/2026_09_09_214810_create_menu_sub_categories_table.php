<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A subdivision of a category: Biryani → Chicken, Mutton, Vegetable.
     *
     * The fifth level of the menu, and deliberately the last one: a dish is
     * filed under a category and *optionally* under one of that category's
     * sub-categories, so a restaurant that does not need the extra level never
     * sees it. There is no sub-sub-category, and adding one would mean a
     * self-referencing tree with all the ordering and cycle problems that
     * brings, for a depth no menu has yet asked for.
     *
     * Two composite uniques rather than one, because two different keys point
     * here:
     *
     * - (id, tenant_id) is the tenant boundary, the same pair every level of
     *   the menu carries.
     * - (id, menu_category_id) is what menu_items references, and it is what
     *   makes it a database error for a dish to name a sub-category belonging
     *   to a category it is not filed under. Without it the two columns could
     *   drift and the tree would render a dish under the wrong branch.
     */
    public function up(): void
    {
        Schema::create('menu_sub_categories', function (Blueprint $table): void {
            $table->id();

            // Carried directly as well as through the category, exactly as
            // menu_items carries it: this is the tenant boundary, and the
            // composite key below is what stops the two disagreeing.
            $table->foreignId('tenant_id')->constrained('restaurants')->cascadeOnDelete();

            $table->unsignedBigInteger('menu_category_id');

            // One value per App\Enums\Locale case, like every other
            // guest-facing name — see .ai/rules/models.md.
            $table->json('name');

            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['menu_category_id', 'position']);

            $table->unique(['id', 'tenant_id']);
            $table->unique(['id', 'menu_category_id']);

            $table->foreign(['menu_category_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])
                ->on('menu_categories')
                ->cascadeOnDelete();
        });

        // Unique on the English name within one category. An expression index
        // because a plain unique on a JSON column compares whole documents —
        // see .ai/rules/migrations.md.
        DB::statement("CREATE UNIQUE INDEX menu_sub_categories_menu_category_id_name_en_unique ON menu_sub_categories (menu_category_id, (name ->> 'en'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_sub_categories');
    }
};
