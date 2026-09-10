<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One band of the home screen a guest lands on.
     *
     * Tiles used to be a single flat list, which meant every home screen was
     * the same shape: a column of wide rectangles. A restaurant arranging its
     * own shop window wants more than that — a banner into the menu, a rail of
     * photographs, a strip of circular links — so the arrangement is now rows,
     * and the row owns the layout every tile inside it is drawn with.
     *
     * "Row" rather than "section": the panel already calls menu_categories
     * sections, and one word for two different things is how a reader ends up
     * on the wrong page.
     */
    public function up(): void
    {
        Schema::create('home_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('restaurants')->cascadeOnDelete();

            // Optional: a banner row usually speaks for itself, where a rail of
            // photographs wants "This month's offers" over it. Translated like
            // every other word a guest reads.
            $table->json('title')->nullable();

            $table->string('layout', 32);

            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['tenant_id', 'position']);

            // Referenced by the composite foreign key on home_tiles, which is
            // what stops a tile sitting in another restaurant's row.
            $table->unique(['id', 'tenant_id']);
        });

        // A row's title is optional, so two untitled rows are not a clash and
        // the index only constrains the ones that have been named — a partial
        // index, which Postgres builds natively.
        DB::statement("CREATE UNIQUE INDEX home_rows_tenant_id_title_en_unique ON home_rows (tenant_id, (title ->> 'en')) WHERE title IS NOT NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists('home_rows');
    }
};
