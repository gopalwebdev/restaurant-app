<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Postgres types and guarantees for home screen tiles.
     *
     * A tile's action and its destination are paired: exactly the column its
     * action uses is filled, and the other two are empty. HomeTile::booted()
     * and App\Enums\HomeTileAction::targetColumn() have always said so; this is
     * the database saying it as well, for anything that writes a tile without
     * going through the model. The values are written out rather than read from
     * the enum, because a migration has to mean the same thing when it is run
     * again after the enum has grown — a new action is a new migration replacing
     * this constraint.
     */
    public function up(): void
    {
        Schema::table('home_tiles', function (Blueprint $table): void {
            $table->jsonb('label')->using('label::jsonb')->change();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE home_tiles ADD CONSTRAINT home_tiles_destination_matches_action CHECK (
                (action = 'menu' AND menu_id IS NOT NULL AND document_path IS NULL AND url IS NULL)
                OR (action = 'pdf' AND document_path IS NOT NULL AND menu_id IS NULL AND url IS NULL)
                OR (action = 'link' AND url IS NOT NULL AND menu_id IS NULL AND document_path IS NULL)
            )
            SQL);

        DB::statement('ALTER TABLE home_tiles ADD CONSTRAINT home_tiles_position_not_negative CHECK (position >= 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE home_tiles DROP CONSTRAINT IF EXISTS home_tiles_position_not_negative');
        DB::statement('ALTER TABLE home_tiles DROP CONSTRAINT IF EXISTS home_tiles_destination_matches_action');

        Schema::table('home_tiles', function (Blueprint $table): void {
            $table->json('label')->using('label::json')->change();
        });
    }
};
