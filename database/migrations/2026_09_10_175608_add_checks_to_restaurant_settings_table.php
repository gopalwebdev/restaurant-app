<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A restaurant's rates are 0% to 100%, and its parcel charge is not negative.
     *
     * Both charges are nullable because a switch turns each one off; a charge
     * that is on is still held to the same range.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE restaurant_settings ADD CONSTRAINT restaurant_settings_tax_rate_in_range CHECK (tax_rate_basis_points BETWEEN 0 AND 10000)');
        DB::statement('ALTER TABLE restaurant_settings ADD CONSTRAINT restaurant_settings_service_charge_in_range CHECK (service_charge_basis_points IS NULL OR service_charge_basis_points BETWEEN 0 AND 10000)');
        DB::statement('ALTER TABLE restaurant_settings ADD CONSTRAINT restaurant_settings_parcel_charge_not_negative CHECK (parcel_charge_minor_units IS NULL OR parcel_charge_minor_units >= 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE restaurant_settings DROP CONSTRAINT IF EXISTS restaurant_settings_parcel_charge_not_negative');
        DB::statement('ALTER TABLE restaurant_settings DROP CONSTRAINT IF EXISTS restaurant_settings_service_charge_in_range');
        DB::statement('ALTER TABLE restaurant_settings DROP CONSTRAINT IF EXISTS restaurant_settings_tax_rate_in_range');
    }
};
