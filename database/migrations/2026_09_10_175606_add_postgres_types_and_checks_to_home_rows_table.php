<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Postgres types and guarantees for home screen rows.
     *
     * The optional title becomes `jsonb`; its partial unique index on
     * `(tenant_id, (title ->> 'en')) WHERE title IS NOT NULL` is rebuilt by the
     * type change.
     */
    public function up(): void
    {
        Schema::table('home_rows', function (Blueprint $table): void {
            $table->jsonb('title')->nullable()->using('title::jsonb')->change();
        });

        DB::statement('ALTER TABLE home_rows ADD CONSTRAINT home_rows_position_not_negative CHECK (position >= 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE home_rows DROP CONSTRAINT IF EXISTS home_rows_position_not_negative');

        Schema::table('home_rows', function (Blueprint $table): void {
            $table->json('title')->nullable()->using('title::json')->change();
        });
    }
};
