<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How a restaurant brands the apps its guests and staff use.
     *
     * Both apps are shadcn, which draws every colour from CSS custom
     * properties, so one stored colour is enough to re-skin them: it is
     * injected as --primary and the component library follows.
     */
    public function up(): void
    {
        Schema::table('restaurant_settings', function (Blueprint $table): void {
            // Seven characters, because it is stored as #RRGGBB. Kept as the
            // hex a colour picker produces rather than converted on the way in,
            // so what the restaurant chose is what comes back out.
            $table->string('theme_primary_color', 7)->default('#E11D48');

            $table->string('theme_appearance', 16)->default('system');
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_settings', function (Blueprint $table): void {
            $table->dropColumn(['theme_primary_color', 'theme_appearance']);
        });
    }
};
