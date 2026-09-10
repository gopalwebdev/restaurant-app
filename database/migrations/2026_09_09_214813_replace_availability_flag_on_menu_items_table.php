<?php

use App\Enums\ItemAvailability;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `is_available` becomes `availability`, which says why.
     *
     * A boolean could only answer "can a guest order this", so a dish the
     * kitchen had run out of and one that was off for the evening were the
     * same row, and both read to a guest as though the dish had been taken off
     * the menu. App\Enums\ItemAvailability carries the reason.
     *
     * The backfill is a single set-based UPDATE rather than a chunked walk:
     * every row gets the default on the way in, and the one statement below
     * corrects the ones that were switched off. Nothing is loaded into PHP at
     * all, which beats even chunkById on the memory rule in
     * .ai/rules/general.md.
     */
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->string('availability', 32)
                ->default(ItemAvailability::Available->value)
                ->after('is_available');
        });

        DB::table('menu_items')
            ->where('is_available', false)
            ->update(['availability' => ItemAvailability::OutOfStock->value]);

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropColumn('is_available');
        });
    }

    /**
     * Both reasons for being off collapse back into a single false, which is
     * all the boolean could ever hold.
     */
    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->boolean('is_available')->default(true)->after('availability');
        });

        DB::table('menu_items')
            ->where('availability', '!=', ItemAvailability::Available->value)
            ->update(['is_available' => false]);

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropColumn('availability');
        });
    }
};
