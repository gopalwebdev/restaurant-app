<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * India is the only market for now, so a per-restaurant timezone and a
     * choice of currency are both a false choice: the timezone column was
     * written by three places and read by none — the application timezone
     * comes from APP_TIMEZONE, never from the database — and every restaurant
     * prices in rupees. Dropping the column and normalising the other removes
     * a picker that could never have worked and one that offered options this
     * "for now" does not serve. See .ai/rules/app.md.
     */
    public function up(): void
    {
        // Normalise before dropping the timezone column so a rollback of this
        // migration recreates it with the value every row already had.
        DB::table('restaurant_settings')
            ->where('currency', '!=', 'INR')
            ->update(['currency' => 'INR']);

        Schema::table('restaurant_settings', function (Blueprint $table): void {
            $table->dropColumn('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_settings', function (Blueprint $table): void {
            $table->string('timezone', 64)->default('UTC');
        });

        DB::table('restaurant_settings')->update(['timezone' => 'Asia/Kolkata']);
    }
};
