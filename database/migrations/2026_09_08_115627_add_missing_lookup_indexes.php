<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Index the columns that are looked up by on their own.
     *
     * PostgreSQL indexes the target of a foreign key, never the column holding
     * it, and a composite index only serves a query that leads with its first
     * column. Both gaps show up here as sequential scans on hot paths.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // The platform users table filters, sorts and eager-loads by
            // tenant, and dropping a restaurant sets this null across every
            // account. The foreign key alone gave none of that an index.
            $table->index('tenant_id');
        });

        Schema::table('restaurant_user', function (Blueprint $table): void {
            // The unique index leads with restaurant_id, so it answers "who
            // staffs this restaurant" and not "which restaurants does this
            // person staff" — which is what canAccessPanel() asks on every
            // single request into either panel.
            $table->index('user_id');
        });

        Schema::table('role_has_permissions', function (Blueprint $table): void {
            // The primary key leads with permission_id, so reading a role's own
            // permissions had nothing to use. That is every permission cache
            // rebuild, and the roles table's permissions_count.
            $table->index('role_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id']);
        });

        Schema::table('restaurant_user', function (Blueprint $table): void {
            $table->dropIndex(['user_id']);
        });

        Schema::table('role_has_permissions', function (Blueprint $table): void {
            $table->dropIndex(['role_id']);
        });
    }
};
