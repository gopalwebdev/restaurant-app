<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A restaurant's role limits are never negative.
     *
     * Zero is allowed: a restaurant that takes no staff accounts says so.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE restaurants ADD CONSTRAINT restaurants_role_limits_not_negative CHECK (max_admins >= 0 AND max_staff >= 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE restaurants DROP CONSTRAINT IF EXISTS restaurants_role_limits_not_negative');
    }
};
