<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The extras a dish may be ordered with: extra cheese, a large portion.
     *
     * A flat list per dish, deliberately. Grouped choices with rules — "pick
     * exactly one size", "up to three toppings" — are a real thing a menu
     * eventually needs, and they belong above this table rather than inside it,
     * so nothing here has to be reshaped to add them.
     */
    public function up(): void
    {
        Schema::create('menu_item_additions', function (Blueprint $table): void {
            $table->id();

            // Carried directly for the same reason menu_items carries it: this
            // is the tenant boundary, and the composite foreign key below is
            // what makes the two columns impossible to disagree about.
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();

            $table->unsignedBigInteger('menu_item_id');

            $table->json('name');

            // What the addition costs on top of the dish, in the currency's
            // minor unit, exactly as menu_items.price_minor_units. Zero is a
            // real answer: "no onions" is an addition that costs nothing.
            $table->unsignedInteger('price_minor_units')->default(0);

            $table->boolean('is_available')->default(true);
            $table->unsignedInteger('position')->default(0);

            $table->timestamps();

            $table->index(['menu_item_id', 'position']);

            $table->foreign(['menu_item_id', 'restaurant_id'])
                ->references(['id', 'restaurant_id'])
                ->on('menu_items')
                ->cascadeOnDelete();
        });

        // Unique on the English name within one dish — see the note in
        // create_menus_table for why this is an expression index rather than
        // a plain unique on a JSON column.
        DB::statement("CREATE UNIQUE INDEX menu_item_additions_menu_item_id_name_en_unique ON menu_item_additions (menu_item_id, (name ->> 'en'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_additions');
    }
};
