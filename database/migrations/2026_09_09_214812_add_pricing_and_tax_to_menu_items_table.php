<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What a dish used to cost, and what it is taxed at.
     *
     * `compare_at_price_minor_units` is the higher price shown struck through
     * beside the real one — "₹360 ₹299". Named for what it is rather than
     * "strike price", which in every other software context means the exercise
     * price of an option. Nullable because most dishes never have one, and null
     * is the honest way to say "not on offer"; a zero would be a price of
     * nothing. Stored in minor units like every other money column, and the
     * application refuses one that is not above the price being charged.
     *
     * `tax_rate_basis_points` is the dish's own GST rate, typed as a percentage
     * and stored as basis points — 5% is 500. Basis points because the whole
     * tax calculation then stays in integers, exactly as money does, and no
     * float ever reaches a column that has to reconcile to the paisa.
     *
     * It is a plain number rather than a fixed set of slabs on purpose. India's
     * GST 2.0 reform of September 2025 collapsed the old 0/5/12/18/28 slabs to
     * 0/5/18 plus a 40% demerit rate — so a hardcoded list of slabs is one
     * government notification away from being wrong, and was already wrong when
     * this was written. A restaurant types the rate its accountant gives it.
     *
     * Nullable, and null means "use the restaurant's default" from
     * restaurant_settings — the right answer for almost every dish, so a rate is
     * set once rather than on every row.
     *
     * `hsn_code` is the HSN (goods) or SAC (services) code an Indian tax invoice
     * carries per line. Restaurant service is SAC 996331; packaged goods sold
     * alongside carry their own. Eight characters because HSN codes run 4, 6 or
     * 8 digits depending on turnover.
     */
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->unsignedInteger('compare_at_price_minor_units')->nullable()->after('price_minor_units');
            $table->unsignedSmallInteger('tax_rate_basis_points')->nullable()->after('compare_at_price_minor_units');
            $table->string('hsn_code', 8)->nullable()->after('tax_rate_basis_points');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropColumn(['compare_at_price_minor_units', 'tax_rate_basis_points', 'hsn_code']);
        });
    }
};
