<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Settings live beside the restaurant rather than on it: the
        // restaurants table stays about identity and tenancy, and this table
        // is free to grow as more of the storefront becomes configurable.
        Schema::create('restaurant_settings', function (Blueprint $table): void {
            $table->id();

            // Unique, so one restaurant can never end up with two settings rows.
            $table->foreignId('restaurant_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 32)->nullable();
            $table->string('timezone', 64)->default('UTC');
            $table->string('currency', 3)->default('INR');
            $table->boolean('accepts_orders')->default(true);
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurant_settings');
    }
};
