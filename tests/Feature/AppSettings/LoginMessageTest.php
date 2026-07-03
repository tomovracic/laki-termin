<?php

use App\Models\AppSetting;
use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function attachAdminRoleForLoginMessage(User $user): void
{
    $role = Role::query()->firstOrCreate(['name' => 'admin']);
    $user->roles()->syncWithoutDetaching([$role->id]);
}

test('admin can save login message', function () {
    $admin = User::factory()->create();
    attachAdminRoleForLoginMessage($admin);

    $response = $this->actingAs($admin)->patchJson(route('app-settings.login-message.update'), [
        'login_message' => 'Dobrodosli u sustav rezervacija.',
    ]);

    $response
        ->assertSuccessful()
        ->assertJsonPath('data.login_message', 'Dobrodosli u sustav rezervacija.');

    expect(AppSetting::instance()->login_message)->toBe('Dobrodosli u sustav rezervacija.');
});

test('admin can clear login message', function () {
    $admin = User::factory()->create();
    attachAdminRoleForLoginMessage($admin);

    AppSetting::instance()->update(['login_message' => 'Stara poruka']);

    $response = $this->actingAs($admin)->patchJson(route('app-settings.login-message.update'), [
        'login_message' => '',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.login_message', null);

    expect(AppSetting::instance()->login_message)->toBeNull();
});

test('non-admin cannot update login message', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->patchJson(route('app-settings.login-message.update'), [
        'login_message' => 'Neovlastena poruka',
    ]);

    $response->assertForbidden();
});

test('login message is shared after successful login', function () {
    $user = User::factory()->create();

    AppSetting::instance()->update([
        'login_message' => 'Vazna obavijest za sve korisnike.',
    ]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('loginMessage', 'Vazna obavijest za sve korisnike.'));
});

test('empty login message is not shared after login', function () {
    $user = User::factory()->create();

    AppSetting::instance()->update([
        'login_message' => null,
    ]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('loginMessage', null));
});

test('admin users page includes current login message', function () {
    $admin = User::factory()->create();
    attachAdminRoleForLoginMessage($admin);

    AppSetting::instance()->update([
        'login_message' => 'Poruka za korisnike',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.users'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('login_message', 'Poruka za korisnike'));
});
