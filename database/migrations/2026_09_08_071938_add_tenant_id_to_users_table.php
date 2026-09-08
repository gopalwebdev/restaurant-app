<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The restaurant an account belongs to. The product team have none, which
        // is what leaves this null for them.
        //
        // This does not replace restaurant_user: someone may staff more than one
        // restaurant, and the pivot is still what says which. This column names
        // the one they belong to, which is what the product team panel lists them by.
        //
        // Note that a null tenant does not by itself put someone on the product
        // team — users.is_super_admin is the only thing that does. An account
        // created before it is put on a roster has no tenant and no powers.
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('tenant_id')
                ->nullable()
                ->after('email_verified_at')
                ->constrained('restaurants')
                ->nullOnDelete();
        });

        $this->backfillFromRosters();
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tenant_id');
        });
    }

    /**
     * Give every existing account the restaurant it already staffs.
     *
     * Anyone on more than one roster gets the first they joined; the product team
     * are on none and keep a null tenant.
     */
    private function backfillFromRosters(): void
    {
        DB::table('users')->orderBy('id')->each(function (object $user): void {
            $restaurantId = DB::table('restaurant_user')
                ->where('user_id', $user->id)
                ->orderBy('id')
                ->value('restaurant_id');

            if ($restaurantId === null) {
                return;
            }

            DB::table('users')->where('id', $user->id)->update(['tenant_id' => $restaurantId]);
        });
    }
};
