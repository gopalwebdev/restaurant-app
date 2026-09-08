<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * The roles that no longer exist, and what each becomes.
     *
     * A manager ran a restaurant day to day and held user.manage, which only
     * admin still carries, so they become an admin rather than being demoted to
     * staff and quietly losing people management. A customer is what a guest now
     * is, and the permissions are identical.
     */
    private const array REPLACEMENTS = [
        'manager' => 'admin',
        'customer' => 'guest',
    ];

    public function up(): void
    {
        foreach (self::REPLACEMENTS as $retired => $replacement) {
            $this->moveHolders($retired, $replacement);
        }

        DB::table('roles')->whereIn('name', array_keys(self::REPLACEMENTS))->delete();

        $this->forgetCachedPermissions();
    }

    /**
     * Reversing this puts the roles back, empty.
     *
     * Who held them is not recoverable — up() moved those people onto the
     * replacement role and nothing recorded where they came from — so this
     * restores the rows and no more.
     */
    public function down(): void
    {
        $guard = config('auth.defaults.guard');

        foreach (array_keys(self::REPLACEMENTS) as $retired) {
            DB::table('roles')->insertOrIgnore([
                'name' => $retired,
                'guard_name' => $guard,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->forgetCachedPermissions();
    }

    /**
     * Give everyone on a retired role its replacement instead.
     */
    private function moveHolders(string $retired, string $replacement): void
    {
        $retiredId = DB::table('roles')->where('name', $retired)->value('id');
        $replacementId = DB::table('roles')->where('name', $replacement)->value('id');

        if ($retiredId === null || $replacementId === null) {
            return;
        }

        $assignments = DB::table('model_has_roles')->where('role_id', $retiredId)->get();

        foreach ($assignments as $assignment) {
            // insertOrIgnore rather than update: someone may already hold the
            // replacement, and the pivot is uniquely keyed.
            DB::table('model_has_roles')->insertOrIgnore([
                'role_id' => $replacementId,
                'model_type' => $assignment->model_type,
                'model_id' => $assignment->model_id,
            ]);
        }

        DB::table('model_has_roles')->where('role_id', $retiredId)->delete();
        DB::table('role_has_permissions')->where('role_id', $retiredId)->delete();
    }

    /**
     * The registrar caches roles and their permissions, and every row this
     * migration touched is in that cache.
     */
    private function forgetCachedPermissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
