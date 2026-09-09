<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The home screen a guest lands on after scanning the QR code at a table.
     *
     * Tiles are the restaurant's own arrangement — it chooses the pictures, the
     * order, and what each one opens — so this is a table rather than a fixed
     * screen. A tile is one picture and one destination; nothing more.
     */
    public function up(): void
    {
        Schema::create('home_tiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();

            // Read out by screen readers, shown when there is no image yet, and
            // used as the heading of the page a PDF tile opens.
            $table->json('label');

            // Both files live on the private disk and are streamed through
            // tenant-checked routes, so a tile's picture and its PDF are only
            // ever reachable through the restaurant they belong to.
            $table->string('image_path')->nullable();
            $table->string('document_path')->nullable();

            // Dropped again by add_rows_and_links_to_home_tiles_table: the row
            // a tile sits in owns its layout now. Literal rather than an enum
            // reference, because App\Enums\HomeTileShape no longer exists.
            $table->string('shape', 32)->default('rectangle');
            $table->string('action', 32);

            // The destination of a Menu tile. Composite again: a tile can only
            // open a menu belonging to the restaurant whose home screen it is.
            $table->unsignedBigInteger('menu_id')->nullable();

            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['restaurant_id', 'position']);

            $table->foreign(['menu_id', 'restaurant_id'])
                ->references(['id', 'restaurant_id'])
                ->on('menus')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_tiles');
    }
};
