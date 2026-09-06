<?php

use App\Enums\AdminPanel;
use App\Enums\Role;
use App\Filament\Admin\Auth\Login as AdminLogin;
use App\Filament\SuperAdmin\Auth\Login as SuperAdminLogin;
use App\Models\Restaurant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->readCodes = captureIssuedCodes();
});

/**
 * A platform administrator, who may use the super admin panel.
 */
function superAdmin(): User
{
    return User::factory()->superAdmin()->create();
}

/**
 * A restaurant administrator, who may use only their own tenant panel.
 */
function restaurantAdmin(Restaurant $restaurant): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::Admin->value);
    $user->restaurants()->attach($restaurant);

    return $user;
}

/*
|--------------------------------------------------------------------------
| Requesting a code
|--------------------------------------------------------------------------
*/

it('issues a code and moves on to asking for it', function (): void {
    Filament::setCurrentPanel(AdminPanel::SuperAdmin->value);
    $user = superAdmin();

    Livewire::test(SuperAdminLogin::class)
        ->fillForm(['email' => $user->email])
        ->call('requestCode')
        ->assertHasNoFormErrors()
        ->assertSet('hasRequestedCode', true);

    expect(($this->readCodes)())->toHaveCount(1);
});

it('matches the address regardless of how it is capitalised', function (): void {
    Filament::setCurrentPanel(AdminPanel::SuperAdmin->value);
    $user = superAdmin();

    Livewire::test(SuperAdminLogin::class)
        ->fillForm(['email' => strtoupper($user->email)])
        ->call('requestCode');

    expect(($this->readCodes)())->toHaveCount(1);
});

it('turns down an address with no account behind it', function (): void {
    Filament::setCurrentPanel(AdminPanel::SuperAdmin->value);

    Livewire::test(SuperAdminLogin::class)
        ->fillForm(['email' => 'nobody@example.com'])
        ->call('requestCode')
        ->assertHasFormErrors(['email'])
        ->assertSet('hasRequestedCode', false);

    expect(($this->readCodes)())->toBeEmpty();
});

it('turns down an address that is not an email at all', function (): void {
    Filament::setCurrentPanel(AdminPanel::SuperAdmin->value);

    Livewire::test(SuperAdminLogin::class)
        ->fillForm(['email' => 'not-an-email'])
        ->call('requestCode')
        ->assertHasFormErrors(['email' => 'email'])
        ->assertSet('hasRequestedCode', false);

    expect(($this->readCodes)())->toBeEmpty();
});

it('issues no code to a user who cannot reach the panel', function (): void {
    Filament::setCurrentPanel(AdminPanel::SuperAdmin->value);
    $user = restaurantAdmin(Restaurant::factory()->create(['slug' => 't1']));

    // A real account, but not one that can use this panel. It is refused in
    // exactly the same words as an address nobody owns.
    Livewire::test(SuperAdminLogin::class)
        ->fillForm(['email' => $user->email])
        ->call('requestCode')
        ->assertHasFormErrors(['email'])
        ->assertSet('hasRequestedCode', false);

    expect(($this->readCodes)())->toBeEmpty();
});

it('will not request a code without an address', function (): void {
    Filament::setCurrentPanel(AdminPanel::SuperAdmin->value);

    Livewire::test(SuperAdminLogin::class)
        ->fillForm(['email' => ''])
        ->call('requestCode')
        ->assertHasFormErrors(['email' => 'required']);

    expect(($this->readCodes)())->toBeEmpty();
});

it('sends a new code without the code field being filled in', function (): void {
    Filament::setCurrentPanel(AdminPanel::SuperAdmin->value);
    $user = superAdmin();

    $component = Livewire::test(SuperAdminLogin::class)
        ->fillForm(['email' => $user->email])
        ->call('requestCode');

    $this->travel((int) config('otp.resend_cooldown') + 1)->seconds();

    // The code field is visible and required by now, but asking for a
    // replacement must not trip over it being empty.
    $component->call('requestCode')->assertHasNoFormErrors();

    expect(($this->readCodes)())->toHaveCount(2);
});

it('turns down a second code asked for inside the cooldown', function (): void {
    Filament::setCurrentPanel(AdminPanel::SuperAdmin->value);
    $user = superAdmin();

    Livewire::test(SuperAdminLogin::class)
        ->fillForm(['email' => $user->email])
        ->call('requestCode')
        ->call('requestCode')
        ->assertHasNoFormErrors();

    expect(($this->readCodes)())->toHaveCount(1);
});

it('stops issuing codes once the allowance is used up', function (): void {
    Filament::setCurrentPanel(AdminPanel::SuperAdmin->value);
    $user = superAdmin();
    $maxSends = (int) config('otp.max_sends');

    $component = Livewire::test(SuperAdminLogin::class)
        ->fillForm(['email' => $user->email]);

    // One more request than the allowance, each after the cooldown has passed.
    for ($request = 0; $request <= $maxSends; $request++) {
        $component->call('requestCode');
        $this->travel((int) config('otp.resend_cooldown') + 1)->seconds();
    }

    expect(($this->readCodes)())->toHaveCount($maxSends);
});

it('goes back to the address step on request', function (): void {
    Filament::setCurrentPanel(AdminPanel::SuperAdmin->value);
    $user = superAdmin();

    Livewire::test(SuperAdminLogin::class)
        ->fillForm(['email' => $user->email])
        ->call('requestCode')
        ->assertSet('hasRequestedCode', true)
        ->call('startOver')
        ->assertSet('hasRequestedCode', false)
        ->assertFormSet(['email' => $user->email]);
});

/*
|--------------------------------------------------------------------------
| The code step
|--------------------------------------------------------------------------
*/

it('holds back the sign-in button until every digit is typed', function (): void {
    Filament::setCurrentPanel(AdminPanel::SuperAdmin->value);
    $user = superAdmin();
    $length = (int) config('otp.length');

    $component = Livewire::test(SuperAdminLogin::class)
        ->fillForm(['email' => $user->email])
        ->call('requestCode');

    $component->fillForm(['code' => str_repeat('1', $length - 1)])
        ->assertDontSee('Sign in');

    $component->fillForm(['code' => str_repeat('1', $length)])
        ->assertSee('Sign in');
});

it('says how many digits the code has', function (): void {
    Filament::setCurrentPanel(AdminPanel::SuperAdmin->value);
    $user = superAdmin();

    Livewire::test(SuperAdminLogin::class)
        ->fillForm(['email' => $user->email])
        ->call('requestCode')
        ->assertSee(sprintf('%d-digit code', (int) config('otp.length')));
});

it('turns down a code that is not all digits', function (): void {
    Filament::setCurrentPanel(AdminPanel::SuperAdmin->value);
    $user = superAdmin();

    $component = Livewire::test(SuperAdminLogin::class)
        ->fillForm(['email' => $user->email])
        ->call('requestCode');

    $component
        ->fillForm(['code' => str_repeat('a', (int) config('otp.length'))])
        ->call('authenticate')
        ->assertHasFormErrors(['code']);

    $this->assertGuest();
});

/*
|--------------------------------------------------------------------------
| Signing in
|--------------------------------------------------------------------------
*/

it('signs a super admin in with the code that was issued', function (): void {
    Filament::setCurrentPanel(AdminPanel::SuperAdmin->value);
    $user = superAdmin();

    $component = Livewire::test(SuperAdminLogin::class)
        ->fillForm(['email' => $user->email])
        ->call('requestCode');

    $component
        ->fillForm(['email' => $user->email, 'code' => ($this->readCodes)()[0]])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($user);
});

it('signs a restaurant admin into their own tenant panel', function (): void {
    $restaurant = Restaurant::factory()->create(['slug' => 't1']);
    Filament::setCurrentPanel(AdminPanel::Admin->value);
    $user = restaurantAdmin($restaurant);

    $component = Livewire::test(AdminLogin::class)
        ->fillForm(['email' => $user->email])
        ->call('requestCode');

    $component
        ->fillForm(['email' => $user->email, 'code' => ($this->readCodes)()[0]])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($user);
});

it('marks the address verified once a code has been used', function (): void {
    Filament::setCurrentPanel(AdminPanel::SuperAdmin->value);
    $user = superAdmin();
    $user->forceFill(['email_verified_at' => null])->save();

    $component = Livewire::test(SuperAdminLogin::class)
        ->fillForm(['email' => $user->email])
        ->call('requestCode');

    $component
        ->fillForm(['email' => $user->email, 'code' => ($this->readCodes)()[0]])
        ->call('authenticate');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('turns the first submit into a code request', function (): void {
    Filament::setCurrentPanel(AdminPanel::SuperAdmin->value);
    $user = superAdmin();

    Livewire::test(SuperAdminLogin::class)
        ->fillForm(['email' => $user->email])
        ->call('authenticate')
        ->assertSet('hasRequestedCode', true);

    expect(($this->readCodes)())->toHaveCount(1);
    $this->assertGuest();
});

/*
|--------------------------------------------------------------------------
| Rejecting a code
|--------------------------------------------------------------------------
*/

it('rejects a code that is not the one issued', function (): void {
    Filament::setCurrentPanel(AdminPanel::SuperAdmin->value);
    $user = superAdmin();

    $component = Livewire::test(SuperAdminLogin::class)
        ->fillForm(['email' => $user->email])
        ->call('requestCode');

    $wrongCode = str_pad((string) ((int) ($this->readCodes)()[0] + 1), 6, '0', STR_PAD_LEFT);

    $component
        ->fillForm(['email' => $user->email, 'code' => $wrongCode])
        ->call('authenticate')
        ->assertHasFormErrors(['code']);

    $this->assertGuest();
});

it('rejects a code that has expired', function (): void {
    Filament::setCurrentPanel(AdminPanel::SuperAdmin->value);
    $user = superAdmin();

    $component = Livewire::test(SuperAdminLogin::class)
        ->fillForm(['email' => $user->email])
        ->call('requestCode');

    $code = ($this->readCodes)()[0];

    $this->travel((int) config('otp.ttl') + 1)->minutes();

    $component
        ->fillForm(['email' => $user->email, 'code' => $code])
        ->call('authenticate')
        ->assertHasFormErrors(['code']);

    $this->assertGuest();
});

it('re-checks panel access when the code is submitted', function (): void {
    Filament::setCurrentPanel(AdminPanel::SuperAdmin->value);
    $user = superAdmin();

    $component = Livewire::test(SuperAdminLogin::class)
        ->fillForm(['email' => $user->email])
        ->call('requestCode');

    $code = ($this->readCodes)()[0];

    // Access is taken away between asking for the code and using it.
    $user->forceFill(['is_super_admin' => false])->save();

    $component
        ->fillForm(['email' => $user->email, 'code' => $code])
        ->call('authenticate')
        ->assertHasFormErrors(['code']);

    $this->assertGuest();
});

it('will not let a code be used twice', function (): void {
    Filament::setCurrentPanel(AdminPanel::SuperAdmin->value);
    $user = superAdmin();

    $component = Livewire::test(SuperAdminLogin::class)
        ->fillForm(['email' => $user->email])
        ->call('requestCode');

    $code = ($this->readCodes)()[0];

    $component->fillForm(['email' => $user->email, 'code' => $code])->call('authenticate');
    $this->assertAuthenticatedAs($user);

    auth()->logout();

    // Replaying the very same submission on the very same page.
    $component->call('authenticate')->assertHasFormErrors(['code']);

    $this->assertGuest();
});

it('will not accept a code against a swapped-in address', function (): void {
    Filament::setCurrentPanel(AdminPanel::SuperAdmin->value);
    $user = superAdmin();

    $component = Livewire::test(SuperAdminLogin::class)
        ->fillForm(['email' => $user->email])
        ->call('requestCode');

    $code = ($this->readCodes)()[0];

    // The address is read-only on screen, so this is someone editing the
    // request. The code belongs to another address and must not be honoured.
    $component
        ->fillForm(['email' => 'nobody@example.com', 'code' => $code])
        ->call('authenticate')
        ->assertHasFormErrors(['code']);

    $this->assertGuest();
});
