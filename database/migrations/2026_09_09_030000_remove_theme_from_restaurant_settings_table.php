<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Take theme customisation back out of the admin panel.
     *
     * A restaurant no longer chooses a brand colour or a light/dark default.
     * Light and dark are the whole of the theming, and the choice belongs to
     * whoever is holding the phone — a guest in a dark dining room and one on a
     * bright terrace want different answers, and neither is the restaurant's to
     * make on their behalf.
     *
     * Both columns were added two days ago and nothing but the settings page
     * read them, so this drops them rather than leaving dead configuration a
     * later reader would have to work out the status of.
     */
    public function up(): void
    {
        Schema::table('restaurant_settings', function (Blueprint $table): void {
            $table->dropColumn(['theme_primary_color', 'theme_appearance']);
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_settings', function (Blueprint $table): void {
            $table->string('theme_primary_color', 7)->default('#E11D48');
            $table->string('theme_appearance', 16)->default('system');
        });
    }
};
