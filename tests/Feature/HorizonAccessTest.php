<?php

use App\Enums\Role;
use App\Models\Restaurant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/*
|--------------------------------------------------------------------------
| Horizon dashboard access
|--------------------------------------------------------------------------
|
| The queue carries jobs for every restaurant on the platform, so the
| dashboard is the product team only.
|
*/

it('lets a super admin open Horizon', function (): void {
    $user = User::factory()->superAdmin()->create();

    expect(Gate::forUser($user)->allows('viewHorizon'))->toBeTrue();
});

it('keeps a restaurant admin out of Horizon', function (): void {
    $restaurant = Restaurant::factory()->create();
    $user = User::factory()->create();
    $user->assignRole(Role::Admin->value);
    $user->restaurants()->attach($restaurant);

    expect(Gate::forUser($user)->allows('viewHorizon'))->toBeFalse();
});

it('keeps a guest out of Horizon', function (): void {
    expect(Gate::allows('viewHorizon'))->toBeFalse();
});

it('sends sign-in mail to its own queue so codes are not stuck behind other work', function (): void {
    $queues = config('horizon.defaults.supervisor-1.queue');

    expect($queues)->toContain('mail')
        ->and($queues[0])->toBe('mail');
});
