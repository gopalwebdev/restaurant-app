<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What a dish used to cost, and what it is taxed at.
     *
     * `strike_price_minor_units` is the higher price shown struck through
     * beside the real one — "₹360 ₹299". Nullable because most dishes never
     * have one, and null is the honest way to say "this is not on offer"; a
     * zero would be a price of nothing. It is stored in minor units like every
     * other money column here (.ai/rules/migrations.md), and the application
     * refuses one that is not above the price it is struck against — a strike
     * price below the real price advertises a discount that does not exist.
     *
     * `tax_rate_basis_points` is the dish's own GST slab, as basis points
     * (5% = 500) cast to App\Enums\TaxRate. Nullable, and null means "use the
     * restaurant's default from restaurant_settings" — which is the right
     * answer for almost every dish, so a restaurant sets 5% once rather than on
     * every row. It is only set per dish where a dish genuinely differs: a
     * sealed bottle of water sold alongside the food is taxed as goods, not as
     * restaurant service.
     *
     * `hsn_code` is the HSN (goods) or SAC (services) code an Indian tax
     * invoice has to carry per line. Restaurant service is SAC 996331; packaged
     * goods carry their own. Nullable, and eight characters because HSN codes
     * run 4, 6 or 8 digits depending on turnover.
     */
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->unsignedInteger('strike_price_minor_units')->nullable()->after('price_minor_units');
            $table->unsignedSmallInteger('tax_rate_basis_points')->nullable()->after('strike_price_minor_units');
            $table->string('hsn_code', 8)->nullable()->after('tax_rate_basis_points');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropColumn(['strike_price_minor_units', 'tax_rate_basis_points', 'hsn_code']);
        });
    }
};
