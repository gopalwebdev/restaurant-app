<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Postgres types and guarantees for combos, on the same terms as dishes.
     */
    public function up(): void
    {
        Schema::table('menu_combos', function (Blueprint $table): void {
            $table->jsonb('name')->using('name::jsonb')->change();
            $table->jsonb('description')->nullable()->using('description::jsonb')->change();
        });

        DB::statement('ALTER TABLE menu_combos ADD CONSTRAINT menu_combos_prices_not_negative CHECK (price_minor_units >= 0 AND (compare_at_price_minor_units IS NULL OR compare_at_price_minor_units >= 0))');
        DB::statement('ALTER TABLE menu_combos ADD CONSTRAINT menu_combos_tax_rate_in_range CHECK (tax_rate_basis_points IS NULL OR tax_rate_basis_points BETWEEN 0 AND 10000)');
        DB::statement('ALTER TABLE menu_combos ADD CONSTRAINT menu_combos_position_not_negative CHECK (position >= 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE menu_combos DROP CONSTRAINT IF EXISTS menu_combos_position_not_negative');
        DB::statement('ALTER TABLE menu_combos DROP CONSTRAINT IF EXISTS menu_combos_tax_rate_in_range');
        DB::statement('ALTER TABLE menu_combos DROP CONSTRAINT IF EXISTS menu_combos_prices_not_negative');

        Schema::table('menu_combos', function (Blueprint $table): void {
            $table->json('name')->using('name::json')->change();
            $table->json('description')->nullable()->using('description::json')->change();
        });
    }
};
