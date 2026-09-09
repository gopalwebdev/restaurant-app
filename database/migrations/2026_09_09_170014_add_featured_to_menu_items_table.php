<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The dishes a menu leads with.
     *
     * A guest opening a menu sees the restaurant's own picks first, above the
     * sections — the thing it wants to sell tonight, not whatever happens to
     * sit at the top of the first category. Featuring is per dish rather than
     * a table of its own: a dish is either led with or it is not, and the
     * order they are led in is the second half of the same answer.
     *
     * `featured_position` is separate from `position`, which orders a dish
     * inside its section. The two orders are different questions and a dish
     * answers both at once.
     */
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->boolean('is_featured')->default(false)->after('is_available');
            $table->unsignedInteger('featured_position')->default(0)->after('is_featured');
        });

        Schema::table('menu_items', function (Blueprint $table): void {
            // The guest app's first query on a menu is "what is featured here",
            // and it is answered per restaurant.
            $table->index(['tenant_id', 'is_featured', 'featured_position'], 'menu_items_featured_index');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropIndex('menu_items_featured_index');
            $table->dropColumn(['is_featured', 'featured_position']);
        });
    }
};
