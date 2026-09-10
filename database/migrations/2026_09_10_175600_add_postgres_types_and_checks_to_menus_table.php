<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Postgres types and guarantees for menus.
     *
     * `jsonb` rather than `json` for the translated columns: it is stored
     * parsed, so reading a key does not re-read the document, and it has the
     * equality and ordering operators plain `json` lacks. The expression unique
     * index on `(name ->> 'en')` is rebuilt by Postgres as part of the type
     * change, so it does not need dropping and recreating around it.
     *
     * The CHECK constraints state what the model and the admin form already
     * enforce, so a write that goes around both still cannot land.
     */
    public function up(): void
    {
        Schema::table('menus', function (Blueprint $table): void {
            $table->jsonb('name')->using('name::jsonb')->change();
            $table->jsonb('description')->nullable()->using('description::jsonb')->change();
        });

        // A service window is both halves or neither; Menu::booted() refuses one
        // without the other rather than guessing at the missing half.
        DB::statement('ALTER TABLE menus ADD CONSTRAINT menus_service_window_paired CHECK ((available_from IS NULL) = (available_until IS NULL))');
        DB::statement('ALTER TABLE menus ADD CONSTRAINT menus_positions_not_negative CHECK (position >= 0 AND featured_position >= 0 AND combos_position >= 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE menus DROP CONSTRAINT IF EXISTS menus_positions_not_negative');
        DB::statement('ALTER TABLE menus DROP CONSTRAINT IF EXISTS menus_service_window_paired');

        Schema::table('menus', function (Blueprint $table): void {
            $table->json('name')->using('name::json')->change();
            $table->json('description')->nullable()->using('description::json')->change();
        });
    }
};
