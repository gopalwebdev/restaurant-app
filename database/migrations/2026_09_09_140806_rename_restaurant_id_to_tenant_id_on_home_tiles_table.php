<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One name for the tenant boundary — see the menus migration in this set
     * for why, and for what happens to the constraints built on this column.
     */
    public function up(): void
    {
        Schema::table('home_tiles', function (Blueprint $table): void {
            $table->renameColumn('restaurant_id', 'tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('home_tiles', function (Blueprint $table): void {
            $table->renameColumn('tenant_id', 'restaurant_id');
        });
    }
};
