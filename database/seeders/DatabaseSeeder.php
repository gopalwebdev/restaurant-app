<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the database with one platform owner and one worked example tenant.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        User::factory()
            ->create(['name' => 'Super Admin', 'email' => 'super@example.com'])
            ->assignRole(Role::SuperAdmin->value);

        $restaurant = Restaurant::factory()->create([
            'name' => 'Tenant One',
            'slug' => 't1',
        ]);

        $admin = User::factory()->create([
            'name' => 'Tenant One Admin',
            'email' => 'admin@example.com',
        ]);
        $admin->assignRole(Role::Admin->value);
        $admin->restaurants()->attach($restaurant);

        User::factory()
            ->create(['name' => 'Test User', 'email' => 'test@example.com'])
            ->assignRole(Role::Customer->value);
    }
}
