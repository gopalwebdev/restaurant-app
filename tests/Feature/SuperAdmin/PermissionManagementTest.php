<?php

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use App\Filament\SuperAdmin\Resources\Permissions\Pages\CreatePermission;
use App\Filament\SuperAdmin\Resources\Permissions\Pages\EditPermission;
use App\Filament\SuperAdmin\Resources\Permissions\Pages\ListPermissions;
use App\Filament\SuperAdmin\Resources\Permissions\PermissionResource;
use App\Models\Permission;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/*
|--------------------------------------------------------------------------
| Who may manage permissions
|--------------------------------------------------------------------------
*/

it('lets platform staff manage permissions', function (): void {
    $user = User::factory()->superAdmin()->create();
    $permission = Permission::factory()->create();

    expect($user->can('viewAny', Permission::class))->toBeTrue()
        ->and($user->can('create', Permission::class))->toBeTrue()
        ->and($user->can('update', $permission))->toBeTrue()
        ->and($user->can('delete', $permission))->toBeTrue();
});

it('refuses permission management to every restaurant role', function (RoleEnum $roleEnum): void {
    $user = User::factory()->create();
    $user->assignRole($roleEnum->value);
    $permission = Permission::factory()->create();

    expect($user->can('viewAny', Permission::class))->toBeFalse()
        ->and($user->can('create', Permission::class))->toBeFalse()
        ->and($user->can('update', $permission))->toBeFalse()
        ->and($user->can('delete', $permission))->toBeFalse();
})->with(RoleEnum::cases());

it('keeps a restaurant admin off the permissions page', function (): void {
    $user = User::factory()->create();
    $user->assignRole(RoleEnum::Admin->value);

    $this->actingAs($user)
        ->get('http://restaurant-app.test/super-admin/permissions')
        ->assertForbidden();
});

it('serves the permissions page to platform staff', function (): void {
    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)
        ->get('http://restaurant-app.test/super-admin/permissions')
        ->assertOk();
});

/*
|--------------------------------------------------------------------------
| Creating, editing and deleting
|--------------------------------------------------------------------------
*/

it('lists every permission the application declares', function (): void {
    enterPlatformPanel();

    Livewire::test(ListPermissions::class)
        // More permissions than fit on a page, and this is about all of them.
        ->set('tableRecordsPerPage', 50)
        ->assertCanSeeTableRecords(Permission::query()->get());
});

it('creates a permission', function (): void {
    enterPlatformPanel();

    Livewire::test(CreatePermission::class)
        ->fillForm(['name' => 'table.reserve'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Permission::query()->where('name', 'table.reserve')->exists())->toBeTrue();
});

it('updates a custom permission', function (): void {
    $permission = Permission::factory()->create(['name' => 'table.reserve']);
    enterPlatformPanel();

    Livewire::test(EditPermission::class, ['record' => $permission->getKey()])
        ->fillForm(['name' => 'table.book'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($permission->refresh()->name)->toBe('table.book');
});

it('deletes a custom permission', function (): void {
    $permission = Permission::factory()->create();
    enterPlatformPanel();

    Livewire::test(EditPermission::class, ['record' => $permission->getKey()])
        ->callAction('delete');

    expect(Permission::query()->whereKey($permission->getKey())->exists())->toBeFalse();
});

it('requires a permission name written as subject.ability', function (?string $name): void {
    enterPlatformPanel();

    Livewire::test(CreatePermission::class)
        ->fillForm(['name' => $name])
        ->call('create')
        ->assertHasFormErrors(['name']);
})->with([
    'missing' => null,
    'no ability' => 'table',
    'uppercase' => 'Table.reserve',
    'spaced' => 'table reserve',
    'two dots' => 'table.reserve.now',
]);

it('refuses a permission name that already exists', function (): void {
    enterPlatformPanel();

    Livewire::test(CreatePermission::class)
        ->fillForm(['name' => PermissionEnum::MenuView->value])
        ->call('create')
        ->assertHasFormErrors(['name' => 'unique']);
});

/*
|--------------------------------------------------------------------------
| Built-in permissions are owned by the code
|--------------------------------------------------------------------------
|
| Their names are what the application checks with can(), so renaming or
| deleting one revokes access without saying so.
|
*/

it('refuses to edit or delete a built-in permission, even for platform staff', function (PermissionEnum $permissionEnum): void {
    enterPlatformPanel();

    $permission = Permission::query()->where('name', $permissionEnum->value)->sole();

    expect($permission->isBuiltIn())->toBeTrue()
        ->and(PermissionResource::canEdit($permission))->toBeFalse()
        ->and(PermissionResource::canDelete($permission))->toBeFalse();
})->with(PermissionEnum::cases());

it('allows editing and deleting a custom permission', function (): void {
    enterPlatformPanel();

    $permission = Permission::factory()->create();

    expect($permission->isBuiltIn())->toBeFalse()
        ->and(PermissionResource::canEdit($permission))->toBeTrue()
        ->and(PermissionResource::canDelete($permission))->toBeTrue();
});

it('offers no edit or delete button against a built-in permission', function (): void {
    enterPlatformPanel();

    $permission = Permission::query()->where('name', PermissionEnum::MenuView->value)->sole();

    Livewire::test(ListPermissions::class)
        ->assertTableActionHidden('edit', $permission)
        ->assertTableActionHidden('delete', $permission)
        ->assertTableActionVisible('view', $permission);
});

it('offers edit and delete against a custom permission', function (): void {
    enterPlatformPanel();

    $permission = Permission::factory()->create();

    Livewire::test(ListPermissions::class)
        ->assertTableActionVisible('edit', $permission)
        ->assertTableActionVisible('delete', $permission);
});

it('closes the edit page for a built-in permission', function (): void {
    $user = User::factory()->superAdmin()->create();
    $permission = Permission::query()->where('name', PermissionEnum::MenuView->value)->sole();

    $this->actingAs($user)
        ->get("http://restaurant-app.test/super-admin/permissions/{$permission->getKey()}/edit")
        ->assertForbidden();
});

it('still shows a built-in permission read only', function (): void {
    $user = User::factory()->superAdmin()->create();
    $permission = Permission::query()->where('name', PermissionEnum::MenuView->value)->sole();

    $this->actingAs($user)
        ->get("http://restaurant-app.test/super-admin/permissions/{$permission->getKey()}")
        ->assertOk()
        ->assertSee(PermissionEnum::MenuView->value);
});

it('refuses to rename a built-in permission from anywhere', function (): void {
    $permission = Permission::query()->where('name', PermissionEnum::MenuView->value)->sole();

    expect(fn () => $permission->update(['name' => 'menu.peek']))
        ->toThrow(LogicException::class, 'A built-in permission may not be renamed.');

    expect(Permission::query()->where('name', PermissionEnum::MenuView->value)->exists())->toBeTrue();
});

it('refuses to delete a built-in permission from anywhere', function (): void {
    $permission = Permission::query()->where('name', PermissionEnum::MenuView->value)->sole();

    expect(fn () => $permission->delete())
        ->toThrow(LogicException::class, 'A built-in permission may not be deleted.');

    expect(Permission::query()->where('name', PermissionEnum::MenuView->value)->exists())->toBeTrue();
});
