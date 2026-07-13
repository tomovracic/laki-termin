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

test('login message is shared after successful login when not yet acknowledged', function () {
    $user = User::factory()->create([
        'login_message_acknowledged_at' => null,
    ]);

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

test('login message is not shared after login when already acknowledged', function () {
    $user = User::factory()->create([
        'login_message_acknowledged_at' => now(),
    ]);

    AppSetting::instance()->update([
        'login_message' => 'Vazna obavijest za sve korisnike.',
    ]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('loginMessage', null));
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

test('user can acknowledge login message', function () {
    $user = User::factory()->create([
        'login_message_acknowledged_at' => null,
    ]);

    $this->actingAs($user)
        ->post(route('login-message.acknowledge'))
        ->assertRedirect();

    expect($user->refresh()->login_message_acknowledged_at)->not->toBeNull();
});

test('acknowledging login message is idempotent', function () {
    $acknowledgedAt = now()->subDay();

    AppSetting::instance()->update([
        'login_message' => 'Trenutna poruka.',
        'login_message_updated_at' => $acknowledgedAt,
    ]);

    $user = User::factory()->create([
        'login_message_acknowledged_at' => $acknowledgedAt,
    ]);

    $this->actingAs($user)
        ->post(route('login-message.acknowledge'))
        ->assertRedirect();

    expect($user->refresh()->login_message_acknowledged_at?->toDateTimeString())
        ->toBe($acknowledgedAt->toDateTimeString());
});

test('user can re-acknowledge login message after admin updates it', function () {
    $oldAcknowledgedAt = now()->subDays(2);

    AppSetting::instance()->update([
        'login_message' => 'Nova poruka.',
        'login_message_updated_at' => now()->subDay(),
    ]);

    $user = User::factory()->create([
        'login_message_acknowledged_at' => $oldAcknowledgedAt,
    ]);

    $this->actingAs($user)
        ->post(route('login-message.acknowledge'))
        ->assertRedirect();

    expect($user->refresh()->login_message_acknowledged_at)->not->toBeNull();
    expect($user->login_message_acknowledged_at?->greaterThan($oldAcknowledgedAt))->toBeTrue();
});

test('guest cannot acknowledge login message', function () {
    $this->post(route('login-message.acknowledge'))
        ->assertRedirect(route('login'));
});

test('user sees login message again after admin updates it', function () {
    $user = User::factory()->create([
        'login_message_acknowledged_at' => now()->subDay(),
    ]);

    AppSetting::instance()->update([
        'login_message' => 'Stara poruka.',
        'login_message_updated_at' => now()->subDay(),
    ]);

    app(\App\Services\AppSettingService::class)->updateLoginMessage('Nova poruka od administratora.');

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('loginMessage', 'Nova poruka od administratora.'));
});

test('user does not see login message again when admin saves unchanged message', function () {
    $admin = User::factory()->create();
    attachAdminRoleForLoginMessage($admin);

    $acknowledgedAt = now()->subHour();

    $user = User::factory()->create([
        'login_message_acknowledged_at' => $acknowledgedAt,
    ]);

    AppSetting::instance()->update([
        'login_message' => 'Ista poruka.',
        'login_message_updated_at' => now()->subDay(),
    ]);

    $this->actingAs($admin)->patchJson(route('app-settings.login-message.update'), [
        'login_message' => 'Ista poruka.',
    ])->assertSuccessful();

    auth()->logout();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('loginMessage', null));
});

test('updating login message sets login_message_updated_at', function () {
    $admin = User::factory()->create();
    attachAdminRoleForLoginMessage($admin);

    AppSetting::instance()->update([
        'login_message' => 'Stara poruka.',
        'login_message_updated_at' => null,
    ]);

    $this->actingAs($admin)->patchJson(route('app-settings.login-message.update'), [
        'login_message' => 'Nova poruka.',
    ])->assertSuccessful();

    expect(AppSetting::instance()->refresh()->login_message_updated_at)->not->toBeNull();
});
