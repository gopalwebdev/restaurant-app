<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An addition's own GST slab.
     *
     * Same shape and same meaning as menu_items.tax_rate_basis_points: a
     * percentage typed by the restaurant, stored as basis points, with null
     * meaning "use the restaurant's default". It is a column of its own rather
     * than something inherited from the dish because the two genuinely differ —
     * a bottled drink added to a meal is taxed as goods while the meal beside it
     * is taxed as service, and an invoice has to show each line at its own rate.
     *
     * Additions keep their boolean `is_available` rather than taking the
     * ItemAvailability enum the dishes moved to. An addition that has run out
     * is simply not offered — the guest app leaves it out of the list rather
     * than showing it greyed with a reason — so there is nothing for the extra
     * cases to say here.
     */
    public function up(): void
    {
        Schema::table('menu_item_additions', function (Blueprint $table): void {
            $table->unsignedSmallInteger('tax_rate_basis_points')->nullable()->after('price_minor_units');
        });
    }

    public function down(): void
    {
        Schema::table('menu_item_additions', function (Blueprint $table): void {
            $table->dropColumn('tax_rate_basis_points');
        });
    }
};
