<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();

            $table->string('name');

            // Menus are read in an order the restaurant chooses — starters
            // before desserts — which is never alphabetical and never creation
            // order, so it is a column rather than something inferred.
            $table->unsignedInteger('position')->default(0);

            // Hiding a category takes it off the storefront without deleting it
            // and everything under it, which is what a seasonal menu needs.
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Two categories called Starters in one restaurant is a mistake,
            // and the same name at another restaurant is not.
            $table->unique(['restaurant_id', 'name']);
            $table->index(['restaurant_id', 'position']);

            // Referenced by the composite foreign key on menu_items, which is
            // what stops an item pointing at another restaurant's category.
            $table->unique(['id', 'restaurant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_categories');
    }
};
