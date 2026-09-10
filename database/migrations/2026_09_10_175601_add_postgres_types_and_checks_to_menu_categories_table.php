<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Postgres types and guarantees for both levels of menu category.
     *
     * The name becomes `jsonb`; the level-scoped unique index on
     * `(menu_id, COALESCE(parent_id, 0), (name ->> 'en'))` is rebuilt by the type
     * change itself.
     *
     * A row cannot be its own parent. MenuCategory::booted() already refuses it,
     * and unlike "no third level" this one is expressible without a subquery,
     * so the database says it too.
     */
    public function up(): void
    {
        Schema::table('menu_categories', function (Blueprint $table): void {
            $table->jsonb('name')->using('name::jsonb')->change();
        });

        DB::statement('ALTER TABLE menu_categories ADD CONSTRAINT menu_categories_not_own_parent CHECK (parent_id IS NULL OR parent_id <> id)');
        DB::statement('ALTER TABLE menu_categories ADD CONSTRAINT menu_categories_position_not_negative CHECK (position >= 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE menu_categories DROP CONSTRAINT IF EXISTS menu_categories_position_not_negative');
        DB::statement('ALTER TABLE menu_categories DROP CONSTRAINT IF EXISTS menu_categories_not_own_parent');

        Schema::table('menu_categories', function (Blueprint $table): void {
            $table->json('name')->using('name::json')->change();
        });
    }
};
