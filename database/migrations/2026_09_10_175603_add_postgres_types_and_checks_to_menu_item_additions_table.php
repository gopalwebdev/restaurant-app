<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Postgres types and guarantees for a dish's additions.
     *
     * Zero is a real price here — "no onions" costs nothing and is still worth
     * listing — so the price is not negative rather than positive.
     */
    public function up(): void
    {
        Schema::table('menu_item_additions', function (Blueprint $table): void {
            $table->jsonb('name')->using('name::jsonb')->change();
        });

        DB::statement('ALTER TABLE menu_item_additions ADD CONSTRAINT menu_item_additions_price_not_negative CHECK (price_minor_units >= 0)');
        DB::statement('ALTER TABLE menu_item_additions ADD CONSTRAINT menu_item_additions_tax_rate_in_range CHECK (tax_rate_basis_points IS NULL OR tax_rate_basis_points BETWEEN 0 AND 10000)');
        DB::statement('ALTER TABLE menu_item_additions ADD CONSTRAINT menu_item_additions_position_not_negative CHECK (position >= 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE menu_item_additions DROP CONSTRAINT IF EXISTS menu_item_additions_position_not_negative');
        DB::statement('ALTER TABLE menu_item_additions DROP CONSTRAINT IF EXISTS menu_item_additions_tax_rate_in_range');
        DB::statement('ALTER TABLE menu_item_additions DROP CONSTRAINT IF EXISTS menu_item_additions_price_not_negative');

        Schema::table('menu_item_additions', function (Blueprint $table): void {
            $table->json('name')->using('name::json')->change();
        });
    }
};
