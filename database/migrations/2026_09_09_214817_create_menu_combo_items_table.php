<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What is inside a combo, and how many of each.
     *
     * A pivot with two columns of its own — quantity and position — which is
     * why it is a model rather than a bare belongsToMany: "2 × Coke" and the
     * order the contents are listed in are both things a restaurant sets.
     *
     * Nothing here carries a price. The combo's price is the combo's, and the
     * dishes inside it are named only so a guest can see what they are getting
     * and the kitchen knows what to make. That also means changing a dish's own
     * price never silently changes what a combo costs.
     *
     * Unique on (menu_combo_id, menu_item_id): a dish appears in a combo once,
     * with a quantity. Two rows for the same dish is a mistake that would show
     * as a duplicate line to the guest.
     *
     * Both foreign keys are composite and both carry tenant_id, so a combo can
     * neither contain another restaurant's dish nor be filed under another
     * restaurant's combo — the same guarantee every level of the menu has.
     */
    public function up(): void
    {
        Schema::create('menu_combo_items', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tenant_id')->constrained('restaurants')->cascadeOnDelete();

            $table->unsignedBigInteger('menu_combo_id');
            $table->unsignedBigInteger('menu_item_id');

            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedInteger('position')->default(0);

            $table->timestamps();

            $table->unique(['menu_combo_id', 'menu_item_id']);
            $table->index(['menu_combo_id', 'position']);

            // Postgres does not index a foreign key for you, and the unique
            // above leads with the combo — so without this, deleting a dish
            // (which cascades here) and asking "which combos contain this
            // dish" both scan the whole table.
            $table->index(['menu_item_id', 'tenant_id']);

            $table->foreign(['menu_combo_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])
                ->on('menu_combos')
                ->cascadeOnDelete();

            // Taking a dish off the menu takes it out of every combo that
            // listed it. A combo left advertising a dish that no longer exists
            // is worse than one that is a line shorter.
            $table->foreign(['menu_item_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])
                ->on('menu_items')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_combo_items');
    }
};
