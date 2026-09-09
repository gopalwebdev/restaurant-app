<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * File every section of a menu under the menu it belongs to.
     *
     * The pairing is enforced the same way menu_items already enforces its own:
     * menus carries a unique (id, restaurant_id), and the composite foreign key
     * here references the pair. A section can therefore never sit under another
     * restaurant's menu, whatever a form submits.
     */
    public function up(): void
    {
        // Nullable to begin with, because existing sections have nowhere to
        // point yet. It is made required below, once every row has a menu.
        Schema::table('menu_categories', function (Blueprint $table): void {
            $table->unsignedBigInteger('menu_id')->nullable()->after('restaurant_id');
        });

        $this->giveEveryRestaurantItsFirstMenu();

        Schema::table('menu_categories', function (Blueprint $table): void {
            $table->unsignedBigInteger('menu_id')->nullable(false)->change();
        });

        Schema::table('menu_categories', function (Blueprint $table): void {
            $table->foreign(['menu_id', 'restaurant_id'])
                ->references(['id', 'restaurant_id'])
                ->on('menus')
                ->cascadeOnDelete();

            $table->index(['menu_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::table('menu_categories', function (Blueprint $table): void {
            $table->dropForeign(['menu_id', 'restaurant_id']);
            $table->dropIndex(['menu_id', 'position']);
            $table->dropColumn('menu_id');
        });
    }

    /**
     * Move every existing section onto one menu per restaurant.
     *
     * A restaurant that already had a menu keeps reading as it did: the
     * sections it had are all still there, now under a menu simply called
     * "Menu", which it is free to rename or split in two.
     */
    private function giveEveryRestaurantItsFirstMenu(): void
    {
        $now = now();

        DB::table('menu_categories')
            ->distinct()
            ->pluck('restaurant_id')
            ->each(function (int $restaurantId) use ($now): void {
                $menuId = DB::table('menus')->insertGetId([
                    'restaurant_id' => $restaurantId,
                    'name' => json_encode(['en' => 'Menu'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'description' => null,
                    'position' => 0,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('menu_categories')
                    ->where('restaurant_id', $restaurantId)
                    ->update(['menu_id' => $menuId]);
            });
    }
};
