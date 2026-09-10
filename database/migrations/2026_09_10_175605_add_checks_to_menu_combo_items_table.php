<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A dish is in a combo at least once, and its place is never negative.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE menu_combo_items ADD CONSTRAINT menu_combo_items_quantity_positive CHECK (quantity >= 1)');
        DB::statement('ALTER TABLE menu_combo_items ADD CONSTRAINT menu_combo_items_position_not_negative CHECK (position >= 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE menu_combo_items DROP CONSTRAINT IF EXISTS menu_combo_items_position_not_negative');
        DB::statement('ALTER TABLE menu_combo_items DROP CONSTRAINT IF EXISTS menu_combo_items_quantity_positive');
    }
};
