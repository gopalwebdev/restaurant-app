<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One name for the tenant boundary, everywhere it is carried.
     *
     * `users` has said `tenant_id` since tenancy arrived; every other table
     * said `restaurant_id`, so the same fact wore two names depending on which
     * table you were reading. This is the first of seven, one per table.
     *
     * Both Postgres and SQLite carry a constraint's *definition* through a
     * column rename, so the composite foreign keys that are this schema's
     * tenant isolation survive untouched — only their generated names still
     * read `restaurant_id`, which nothing queries by. The one exception is the
     * expression index below: its name is written out as a literal in
     * `create_menus_table`, so it is dropped and rebuilt rather than left to
     * describe a column that no longer exists.
     */
    public function up(): void
    {
        DB::statement('DROP INDEX menus_restaurant_id_name_en_unique');

        Schema::table('menus', function (Blueprint $table): void {
            $table->renameColumn('restaurant_id', 'tenant_id');
        });

        DB::statement("CREATE UNIQUE INDEX menus_tenant_id_name_en_unique ON menus (tenant_id, (name ->> 'en'))");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX menus_tenant_id_name_en_unique');

        Schema::table('menus', function (Blueprint $table): void {
            $table->renameColumn('tenant_id', 'restaurant_id');
        });

        DB::statement("CREATE UNIQUE INDEX menus_restaurant_id_name_en_unique ON menus (restaurant_id, (name ->> 'en'))");
    }
};
