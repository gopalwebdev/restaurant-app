<?php

use App\Enums\ItemAvailability;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A bundle sold at one price: burger, fries and a drink for ₹299.
     *
     * A combo hangs off the **menu**, not off a category, because that is what
     * it is — a deal the menu leads with, arranged in its own row beside the
     * featured dishes. Filing it under a category would put it in a section a
     * guest has to scroll to, and would make "which category does a burger
     * meal belong to" a question somebody has to answer.
     *
     * Its price is its own and is not derived from the dishes inside it: the
     * whole point of a combo is that it costs less than the sum, and a derived
     * price would either be that sum or a discount rule nobody asked for. The
     * dishes it contains live in menu_combo_items.
     *
     * Everything else mirrors menu_items — money in minor units, an optional
     * struck-through price, an own GST slab that falls back to the
     * restaurant's, and the same availability enum.
     */
    public function up(): void
    {
        Schema::create('menu_combos', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tenant_id')->constrained('restaurants')->cascadeOnDelete();

            $table->unsignedBigInteger('menu_id');

            $table->json('name');
            $table->json('description')->nullable();

            $table->unsignedInteger('price_minor_units');
            $table->unsignedInteger('compare_at_price_minor_units')->nullable();
            $table->unsignedSmallInteger('tax_rate_basis_points')->nullable();

            $table->string('availability', 32)->default(ItemAvailability::Available->value);
            $table->unsignedInteger('position')->default(0);

            $table->timestamps();

            $table->index(['menu_id', 'position']);
            $table->index(['tenant_id', 'position']);

            // Referenced by the composite foreign key on menu_combo_items.
            $table->unique(['id', 'tenant_id']);

            $table->foreign(['menu_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])
                ->on('menus')
                ->cascadeOnDelete();
        });

        // Unique on the English name within one menu, as an expression index —
        // see .ai/rules/migrations.md for why a plain unique cannot work here.
        DB::statement("CREATE UNIQUE INDEX menu_combos_menu_id_name_en_unique ON menu_combos (menu_id, (name ->> 'en'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_combos');
    }
};
