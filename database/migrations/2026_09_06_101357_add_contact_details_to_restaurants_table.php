<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where the platform records a restaurant as a business.
     *
     * These are the details the super admin enters when a restaurant is
     * onboarded: where it trades from and how the platform reaches whoever
     * runs it. They are deliberately not the same thing as the storefront
     * contact details in restaurant_settings, which the restaurant edits
     * itself and guests see.
     */
    public function up(): void
    {
        // Address, pincode and phone are required, and existing rows predate
        // them, so they arrive nullable, get a placeholder, and are only then
        // tightened. A restaurant is unusable until a super admin fills them in.
        Schema::table('restaurants', function (Blueprint $table): void {
            $table->string('address')->nullable()->after('name');
            $table->string('pincode', 16)->nullable()->after('address');
            $table->string('email')->nullable()->after('pincode');
            $table->string('phone', 32)->nullable()->after('email');
            $table->string('secondary_phone', 32)->nullable()->after('phone');
        });

        DB::table('restaurants')
            ->whereNull('phone')
            ->update(['address' => '', 'pincode' => '', 'phone' => '']);

        Schema::table('restaurants', function (Blueprint $table): void {
            $table->string('address')->nullable(false)->change();
            $table->string('pincode', 16)->nullable(false)->change();
            $table->string('phone', 32)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table): void {
            $table->dropColumn(['address', 'pincode', 'email', 'phone', 'secondary_phone']);
        });
    }
};
