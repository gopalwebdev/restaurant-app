<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where the two rails sit among the menu's categories.
     *
     * A menu used to open with its featured dishes, then its combos, then every
     * category — an order nothing could change. These two columns put the rails
     * into the same `position` space `menu_categories.position` already uses, so
     * a restaurant that wants its combos read after Starters can drag them
     * there. App\Enums\MenuBlock::positionColumn() is the only place that says
     * which column belongs to which rail.
     *
     * Both default to 0, which is where a menu's first category sits too —
     * and ties are broken rails first, then in the order the rails are declared
     * (App\Models\Menu::readingOrder()). So a menu nobody has arranged reads
     * exactly as it did before these columns existed: featured dishes, then
     * combos, then every category. One drag renumbers the whole top level and
     * nothing ties again.
     */
    public function up(): void
    {
        Schema::table('menus', function (Blueprint $table): void {
            $table->unsignedInteger('featured_position')->default(0)->after('position');
            $table->unsignedInteger('combos_position')->default(0)->after('featured_position');
        });
    }

    /**
     * Dropping a column rebuilds the table on SQLite, and a rebuild reads the
     * indexes back from `pragma index_info` — which reports columns and not
     * expressions, so the unique on the English name would come back as a
     * unique on tenant_id alone. It is recreated from its real definition here
     * for that reason; on Postgres nothing is rebuilt and this is the same
     * index again. See .ai/rules/migrations.md.
     */
    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table): void {
            $table->dropColumn(['featured_position', 'combos_position']);
        });

        DB::statement('DROP INDEX IF EXISTS menus_tenant_id_name_en_unique');
        DB::statement("CREATE UNIQUE INDEX menus_tenant_id_name_en_unique ON menus (tenant_id, (name ->> 'en'))");
    }
};
