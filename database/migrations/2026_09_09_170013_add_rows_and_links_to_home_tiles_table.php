<?php

use App\Enums\HomeRowLayout;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * File every tile under a row, and let one leave the app.
     *
     * Three changes to one table, so one migration for it:
     *
     * - `shape` goes. The row it sits in decides how a tile is drawn now, and
     *   a per-tile shape could only disagree with its row.
     * - `home_row_id` arrives, paired with tenant_id in a composite foreign
     *   key the same way every other level of this schema is.
     * - `url` arrives as the destination of a Link tile, which is what makes
     *   a strip of Instagram and WhatsApp circles possible.
     */
    public function up(): void
    {
        Schema::table('home_tiles', function (Blueprint $table): void {
            $table->dropColumn('shape');
        });

        Schema::table('home_tiles', function (Blueprint $table): void {
            // Nullable to begin with, because existing tiles have no row to
            // point at yet. Made required below, once every row has one.
            $table->unsignedBigInteger('home_row_id')->nullable()->after('tenant_id');

            $table->string('url')->nullable()->after('document_path');
        });

        $this->giveEveryRestaurantItsFirstRow();

        Schema::table('home_tiles', function (Blueprint $table): void {
            $table->unsignedBigInteger('home_row_id')->nullable(false)->change();
        });

        Schema::table('home_tiles', function (Blueprint $table): void {
            $table->foreign(['home_row_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])
                ->on('home_rows')
                ->cascadeOnDelete();

            $table->index(['home_row_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::table('home_tiles', function (Blueprint $table): void {
            $table->dropForeign(['home_row_id', 'tenant_id']);
            $table->dropIndex(['home_row_id', 'position']);
            $table->dropColumn(['home_row_id', 'url']);
        });

        Schema::table('home_tiles', function (Blueprint $table): void {
            $table->string('shape', 32)->default('rectangle');
        });
    }

    /**
     * Move every existing tile into one banner row per restaurant.
     *
     * A restaurant that had arranged its home screen keeps reading exactly as
     * it did: the tiles it had, in the order it dragged them into, now inside
     * a banner row — which is the layout that draws them the way they were
     * already being drawn.
     */
    private function giveEveryRestaurantItsFirstRow(): void
    {
        $now = now();

        DB::table('home_tiles')
            ->distinct()
            ->pluck('tenant_id')
            ->each(function (int $tenantId) use ($now): void {
                $rowId = DB::table('home_rows')->insertGetId([
                    'tenant_id' => $tenantId,
                    'title' => null,
                    'layout' => HomeRowLayout::Banner->value,
                    'position' => 0,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('home_tiles')
                    ->where('tenant_id', $tenantId)
                    ->update(['home_row_id' => $rowId]);
            });
    }
};
