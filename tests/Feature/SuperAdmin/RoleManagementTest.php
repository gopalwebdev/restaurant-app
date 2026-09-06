<?php

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use App\Filament\SuperAdmin\Resources\Roles\Pages\CreateRole;
use App\Filament\SuperAdmin\Resources\Roles\Pages\EditRole;
use App\Filament\SuperAdmin\Resources\Roles\Pages\ListRoles;
use App\Filament\SuperAdmin\Resources\Roles\RoleResource;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/*
|--------------------------------------------------------------------------
| Who may manage roles
|--------------------------------------------------------------------------
*/

it('lets platform staff manage roles', function (): void {
    $user = User::factory()->superAdmin()->create();
    $role = Role::factory()->create();

    expect($user->can('viewAny', Role::class))->toBeTrue()
        ->and($user->can('create', Role::class))->toBeTrue()
        ->and($user->can('update', $role))->toBeTrue()
        ->and($user->can('delete', $role))->toBeTrue();
});

it('refuses role management to every restaurant role', function (RoleEnum $roleEnum): void {
    $user = User::factory()->create();
    $user->assignRole($roleEnum->value);
    $role = Role::factory()->create();

    expect($user->can('viewAny', Role::class))->toBeFalse()
        ->and($user->can('create', Role::class))->toBeFalse()
        ->and($user->can('update', $role))->toBeFalse()
        ->and($user->can('delete', $role))->toBeFalse();
})->with(RoleEnum::cases());

it('keeps a restaurant admin off the roles page', function (): void {
    $user = User::factory()->create();
    $user->assignRole(RoleEnum::Admin->value);

    $this->actingAs($user)
        ->get('http://restaurant-app.test/super-admin/roles')
        ->assertForbidden();
});

it('serves the roles page to platform staff', function (): void {
    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)
        ->get('http://restaurant-app.test/super-admin/roles')
        ->assertOk();
});

/*
|--------------------------------------------------------------------------
| Creating and editing custom roles
|--------------------------------------------------------------------------
*/

it('lists every role, built in and custom alike', function (): void {
    $custom = Role::factory()->create();
    enterPlatformPanel();

    Livewire::test(ListRoles::class)
        ->assertCanSeeTableRecords(Role::query()->get())
        ->assertCanSeeTableRecords([$custom]);
});

it('creates a role with the permissions it is given', function (): void {
    enterPlatformPanel();

    $menuView = Permission::query()->where('name', PermissionEnum::MenuView->value)->sole();
    $orderManage = Permission::query()->where('name', PermissionEnum::OrderManage->value)->sole();

    Livewire::test(CreateRole::class)
        ->fillForm([
            'name' => 'kitchen-porter',
            'permissions' => [$menuView->getKey(), $orderManage->getKey()],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $role = Role::query()->where('name', 'kitchen-porter')->sole();

    expect($role->permissions->pluck('name')->all())
        ->toEqualCanonicalizing([PermissionEnum::MenuView->value, PermissionEnum::OrderManage->value]);
});

it('updates a custom role', function (): void {
    $role = Role::factory()->create(['name' => 'kitchen-porter']);
    $menuView = Permission::query()->where('name', PermissionEnum::MenuView->value)->sole();
    enterPlatformPanel();

    Livewire::test(EditRole::class, ['record' => $role->getKey()])
        ->fillForm([
            'name' => 'porter',
            'permissions' => [$menuView->getKey()],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($role->refresh()->name)->toBe('porter')
        ->and($role->permissions->pluck('name')->all())->toBe([PermissionEnum::MenuView->value]);
});

it('deletes a custom role', function (): void {
    $role = Role::factory()->create();
    enterPlatformPanel();

    Livewire::test(EditRole::class, ['record' => $role->getKey()])
        ->callAction('delete');

    expect(Role::query()->whereKey($role->getKey())->exists())->toBeFalse();
});

it('requires a role name in the shape code refers to it by', function (?string $name): void {
    enterPlatformPanel();

    Livewire::test(CreateRole::class)
        ->fillForm(['name' => $name])
        ->call('create')
        ->assertHasFormErrors(['name']);
})->with([
    'missing' => null,
    'uppercase' => 'Kitchen',
    'spaced' => 'kitchen porter',
    'underscored' => 'kitchen_porter',
]);

it('refuses a role name that is already taken', function (): void {
    enterPlatformPanel();

    Livewire::test(CreateRole::class)
        ->fillForm(['name' => RoleEnum::Manager->value])
        ->call('create')
        ->assertHasFormErrors(['name' => 'unique']);
});

/*
|--------------------------------------------------------------------------
| Built-in roles are owned by the code
|--------------------------------------------------------------------------
|
| App\Enums\Role declares them and the seeder owns their permissions, so the
| panel offers no way to change one — including to a super admin, who
| Gate::before would otherwise wave past any policy.
|
*/

it('refuses to edit or delete a built-in role, even for platform staff', function (RoleEnum $roleEnum): void {
    enterPlatformPanel();

    $role = Role::findByName($roleEnum->value);

    expect($role->isBuiltIn())->toBeTrue()
        ->and(RoleResource::canEdit($role))->toBeFalse()
        ->and(RoleResource::canDelete($role))->toBeFalse();
})->with(RoleEnum::cases());

it('allows editing and deleting a custom role', function (): void {
    enterPlatformPanel();

    $role = Role::factory()->create();

    expect($role->isBuiltIn())->toBeFalse()
        ->and(RoleResource::canEdit($role))->toBeTrue()
        ->and(RoleResource::canDelete($role))->toBeTrue();
});

it('offers no edit or delete button against a built-in role', function (RoleEnum $roleEnum): void {
    enterPlatformPanel();

    $role = Role::findByName($roleEnum->value);

    // Filament authorises a record action against the policy, which
    // Gate::before answers for a super admin, so without an explicit check the
    // buttons would render and then land on a 403.
    Livewire::test(ListRoles::class)
        ->assertTableActionHidden('edit', $role)
        ->assertTableActionHidden('delete', $role)
        ->assertTableActionVisible('view', $role);
})->with(RoleEnum::cases());

it('offers edit and delete against a custom role', function (): void {
    enterPlatformPanel();

    $role = Role::factory()->create();

    Livewire::test(ListRoles::class)
        ->assertTableActionVisible('edit', $role)
        ->assertTableActionVisible('delete', $role);
});

it('closes the edit page for a built-in role', function (): void {
    $user = User::factory()->superAdmin()->create();
    $role = Role::findByName(RoleEnum::Admin->value);

    $this->actingAs($user)
        ->get("http://restaurant-app.test/super-admin/roles/{$role->getKey()}/edit")
        ->assertForbidden();
});

it('still shows a built-in role read only', function (): void {
    $user = User::factory()->superAdmin()->create();
    $role = Role::findByName(RoleEnum::Admin->value);

    $this->actingAs($user)
        ->get("http://restaurant-app.test/super-admin/roles/{$role->getKey()}")
        ->assertOk()
        ->assertSee(RoleEnum::Admin->value);
});

it('refuses to rename a built-in role from anywhere', function (): void {
    $role = Role::findByName(RoleEnum::Admin->value);

    expect(fn () => $role->update(['name' => 'renamed']))
        ->toThrow(LogicException::class, 'A built-in role may not be renamed.');

    expect(Role::query()->where('name', RoleEnum::Admin->value)->exists())->toBeTrue();
});

it('refuses to delete a built-in role from anywhere', function (): void {
    $role = Role::findByName(RoleEnum::Admin->value);

    expect(fn () => $role->delete())
        ->toThrow(LogicException::class, 'A built-in role may not be deleted.');

    expect(Role::query()->where('name', RoleEnum::Admin->value)->exists())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Which roles a restaurant may hand out
|--------------------------------------------------------------------------
*/

it('withholds any role carrying a platform permission from restaurants', function (): void {
    $platformRole = Role::factory()->create();
    $platformRole->givePermissionTo(PermissionEnum::RestaurantManage->value);

    $assignable = Role::query()->assignableWithinRestaurant()->pluck('name')->all();

    expect($assignable)->not->toContain($platformRole->name)
        ->and($assignable)->toContain(RoleEnum::Admin->value)
        ->and($assignable)->toContain(RoleEnum::Staff->value);
});

it('offers every built-in role to restaurants', function (RoleEnum $roleEnum): void {
    $assignable = Role::query()->assignableWithinRestaurant()->pluck('name')->all();

    expect($assignable)->toContain($roleEnum->value);
})->with(RoleEnum::cases());
