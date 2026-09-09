<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The hours a menu is served between: breakfast 07:00–11:00.
     *
     * Both nullable, and the pair is all-or-nothing — a menu with neither is
     * served whenever the restaurant is open, which is what every menu did
     * before this and what most still do. The model refuses one without the
     * other rather than guessing at the missing half.
     *
     * A window is allowed to run backwards past midnight (22:00–02:00 for a
     * late menu) and Menu::isBeingServedAt() reads it that way, so this is not
     * a CHECK constraint on from < until.
     *
     * Times, not timestamps: this is a daily pattern, not a date. The zone is
     * the application's — Asia/Kolkata from APP_TIMEZONE (.ai/rules/config.md)
     * — because a restaurant has no timezone of its own here, deliberately.
     */
    public function up(): void
    {
        Schema::table('menus', function (Blueprint $table): void {
            $table->time('available_from')->nullable()->after('is_active');
            $table->time('available_until')->nullable()->after('available_from');
        });
    }

    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table): void {
            $table->dropColumn(['available_from', 'available_until']);
        });
    }
};
