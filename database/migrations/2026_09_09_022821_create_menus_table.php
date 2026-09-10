<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The top level of a restaurant's menu: Lunch, Dinner, Drinks.
     *
     * Categories used to hang straight off the restaurant, which meant one
     * restaurant could only ever have one list of sections. A restaurant that
     * serves a different card at lunch had nowhere to put it.
     */
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();

            // Translated columns hold one key per App\Enums\Locale case:
            // {"en": "Dinner", "ta": "இரவு உணவு"}. Spatie's HasTranslations
            // reads and writes them; the guest app is served whichever key
            // matches the visitor's language, falling back to English.
            $table->json('name');
            $table->json('description')->nullable();

            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['restaurant_id', 'position']);

            // Referenced by the composite foreign keys on menu_categories and
            // home_tiles, which is what stops either pointing at another
            // restaurant's menu.
            $table->unique(['id', 'restaurant_id']);
        });

        // A unique on a JSON column would compare whole documents, so two
        // menus both named "Dinner" would be allowed the moment their Tamil
        // halves differed. The constraint belongs on the English name, which
        // every menu is required to have, and that needs an expression index —
        // something Blueprint cannot express and Postgres builds natively.
        DB::statement("CREATE UNIQUE INDEX menus_restaurant_id_name_en_unique ON menus (restaurant_id, (name ->> 'en'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
