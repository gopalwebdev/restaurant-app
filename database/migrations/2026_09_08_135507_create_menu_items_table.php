<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table): void {
            $table->id();

            // Carried directly rather than read through the category, because
            // this is the tenant boundary: every query the panels and the
            // storefront make is "this restaurant's items", and a join to
            // establish that is one an application bug could forget.
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();

            $table->foreignId('menu_category_id');

            $table->string('name');
            $table->text('description')->nullable();

            // Money is an integer count of the minor unit — ₹249.50 is 24950 —
            // so arithmetic stays exact all the way to a payment provider. The
            // currency itself lives on restaurant_settings.currency, and
            // App\Enums\Currency owns the conversion at the edges.
            $table->unsignedInteger('price_minor_units');

            $table->string('food_type');

            // Sold out for the evening, without editing the menu itself.
            $table->boolean('is_available')->default(true);

            $table->unsignedInteger('position')->default(0);

            $table->timestamps();

            $table->unique(['restaurant_id', 'name']);
            $table->index(['menu_category_id', 'position']);

            // The pair, not just the category: menu_categories carries a unique
            // (id, restaurant_id), so this makes it impossible to store an item
            // whose category belongs to a different restaurant. Tenant
            // isolation that a forgotten where() cannot undo.
            $table->foreign(['menu_category_id', 'restaurant_id'])
                ->references(['id', 'restaurant_id'])
                ->on('menu_categories')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
