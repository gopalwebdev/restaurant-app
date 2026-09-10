<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Postgres types and guarantees for dishes.
     *
     * Name and description become `jsonb`. Money is an integer count of the
     * minor unit and can never be negative; a rate is basis points, so 0 to
     * 10000 is 0% to 100%. `unsignedInteger` means nothing to Postgres — it has
     * no unsigned integers — so this is where "never negative" is actually held.
     *
     * The compare-at price is deliberately not constrained to sit above the
     * price: repricing a dish upwards can leave an old offer behind, and
     * MenuItem::hasComparePrice() is what refuses to show one that no longer
     * makes sense.
     */
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->jsonb('name')->using('name::jsonb')->change();
            $table->jsonb('description')->nullable()->using('description::jsonb')->change();
        });

        DB::statement('ALTER TABLE menu_items ADD CONSTRAINT menu_items_prices_not_negative CHECK (price_minor_units >= 0 AND (compare_at_price_minor_units IS NULL OR compare_at_price_minor_units >= 0))');
        DB::statement('ALTER TABLE menu_items ADD CONSTRAINT menu_items_tax_rate_in_range CHECK (tax_rate_basis_points IS NULL OR tax_rate_basis_points BETWEEN 0 AND 10000)');
        DB::statement('ALTER TABLE menu_items ADD CONSTRAINT menu_items_positions_not_negative CHECK (position >= 0 AND (featured_position IS NULL OR featured_position >= 0))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE menu_items DROP CONSTRAINT IF EXISTS menu_items_positions_not_negative');
        DB::statement('ALTER TABLE menu_items DROP CONSTRAINT IF EXISTS menu_items_tax_rate_in_range');
        DB::statement('ALTER TABLE menu_items DROP CONSTRAINT IF EXISTS menu_items_prices_not_negative');

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->json('name')->using('name::json')->change();
            $table->json('description')->nullable()->using('description::json')->change();
        });
    }
};
