<?php

use App\Models\AppSetting;
use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function attachAdminRoleForTerrainUsageRules(User $user): void
{
    $role = Role::query()->firstOrCreate(['name' => 'admin']);
    $user->roles()->syncWithoutDetaching([$role->id]);
}

test('admin can save terrain usage rules in bulk', function () {
    $admin = User::factory()->create();
    attachAdminRoleForTerrainUsageRules($admin);

    $response = $this->actingAs($admin)->patchJson(route('app-settings.terrain-usage-rules.replace'), [
        'rules' => [
            [
                'icon' => 'clock',
                'text' => 'Rezervacije su moguce do 24 sata unaprijed.',
            ],
            [
                'icon' => 'ban',
                'text' => 'Nije dozvoljeno preuzimanje terena bez rezervacije.',
                'emphasis' => 'alert',
            ],
        ],
    ]);

    $response
        ->assertSuccessful()
        ->assertJsonPath('data.terrain_usage_rules.0.icon', 'clock')
        ->assertJsonPath('data.terrain_usage_rules.0.text', 'Rezervacije su moguce do 24 sata unaprijed.')
        ->assertJsonPath('data.terrain_usage_rules.1.icon', 'ban')
        ->assertJsonPath('data.terrain_usage_rules.1.emphasis', 'alert');

    expect(AppSetting::instance()->terrain_usage_rules)->toBe([
        [
            'icon' => 'clock',
            'text' => 'Rezervacije su moguce do 24 sata unaprijed.',
        ],
        [
            'icon' => 'ban',
            'text' => 'Nije dozvoljeno preuzimanje terena bez rezervacije.',
            'emphasis' => 'alert',
        ],
    ]);
});

test('admin can create a terrain usage rule', function () {
    $admin = User::factory()->create();
    attachAdminRoleForTerrainUsageRules($admin);

    AppSetting::instance()->update([
        'terrain_usage_rules' => [
            ['icon' => 'info', 'text' => 'Postojece pravilo'],
        ],
    ]);

    $response = $this->actingAs($admin)->postJson(route('app-settings.terrain-usage-rules.store'), [
        'icon' => 'clock',
        'text' => 'Novo pravilo',
        'emphasis' => 'warning',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.terrain_usage_rules.0.text', 'Postojece pravilo')
        ->assertJsonPath('data.terrain_usage_rules.1.icon', 'clock')
        ->assertJsonPath('data.terrain_usage_rules.1.emphasis', 'warning');

    expect(AppSetting::instance()->terrain_usage_rules)->toHaveCount(2);
});

test('admin can update a terrain usage rule', function () {
    $admin = User::factory()->create();
    attachAdminRoleForTerrainUsageRules($admin);

    AppSetting::instance()->update([
        'terrain_usage_rules' => [
            ['icon' => 'info', 'text' => 'Staro pravilo'],
            ['icon' => 'clock', 'text' => 'Drugo pravilo'],
        ],
    ]);

    $response = $this->actingAs($admin)->patchJson(route('app-settings.terrain-usage-rules.update', ['index' => 0]), [
        'icon' => 'ban',
        'text' => 'Azurirano pravilo',
        'emphasis' => 'alert',
    ]);

    $response
        ->assertSuccessful()
        ->assertJsonPath('data.terrain_usage_rules.0.icon', 'ban')
        ->assertJsonPath('data.terrain_usage_rules.0.text', 'Azurirano pravilo')
        ->assertJsonPath('data.terrain_usage_rules.0.emphasis', 'alert')
        ->assertJsonPath('data.terrain_usage_rules.1.text', 'Drugo pravilo');
});

test('admin can delete a terrain usage rule', function () {
    $admin = User::factory()->create();
    attachAdminRoleForTerrainUsageRules($admin);

    AppSetting::instance()->update([
        'terrain_usage_rules' => [
            ['icon' => 'info', 'text' => 'Prvo pravilo'],
            ['icon' => 'clock', 'text' => 'Drugo pravilo'],
        ],
    ]);

    $response = $this->actingAs($admin)->deleteJson(route('app-settings.terrain-usage-rules.destroy', ['index' => 0]));

    $response
        ->assertSuccessful()
        ->assertJsonPath('data.terrain_usage_rules.0.text', 'Drugo pravilo');

    expect(AppSetting::instance()->terrain_usage_rules)->toHaveCount(1);
});

test('admin cannot update missing terrain usage rule index', function () {
    $admin = User::factory()->create();
    attachAdminRoleForTerrainUsageRules($admin);

    $response = $this->actingAs($admin)->patchJson(route('app-settings.terrain-usage-rules.update', ['index' => 0]), [
        'icon' => 'info',
        'text' => 'Ne postoji',
    ]);

    $response->assertNotFound();
});

test('admin can clear terrain usage rules', function () {
    $admin = User::factory()->create();
    attachAdminRoleForTerrainUsageRules($admin);

    AppSetting::instance()->update([
        'terrain_usage_rules' => [
            ['icon' => 'info', 'text' => 'Staro pravilo'],
        ],
    ]);

    $response = $this->actingAs($admin)->patchJson(route('app-settings.terrain-usage-rules.replace'), [
        'rules' => [],
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.terrain_usage_rules', []);

    expect(AppSetting::instance()->terrain_usage_rules)->toBeNull();
});

test('non-admin cannot update terrain usage rules', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('app-settings.terrain-usage-rules.store'), [
        'icon' => 'info',
        'text' => 'Neovlasteno pravilo',
    ]);

    $response->assertForbidden();
});

test('dashboard includes terrain usage rules', function () {
    $user = User::factory()->create();

    AppSetting::instance()->update([
        'terrain_usage_rules' => [
            ['icon' => 'coins', 'text' => 'Jedna rezervacija koristi jedan token.'],
        ],
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('nav.terrain_usage_rules', 1)
            ->where('nav.terrain_usage_rules.0.icon', 'coins')
            ->where('nav.terrain_usage_rules.0.text', 'Jedna rezervacija koristi jedan token.'),
        );
});

test('admin terrains page includes terrain usage rules', function () {
    $admin = User::factory()->create();
    attachAdminRoleForTerrainUsageRules($admin);

    AppSetting::instance()->update([
        'terrain_usage_rules' => [
            ['icon' => 'calendar', 'text' => 'Rezervirajte termin unaprijed.'],
        ],
    ]);

    $this->actingAs($admin)
        ->get(route('admin.terrains'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('terrain_usage_rules.0.icon', 'calendar')
            ->where('terrain_usage_rules.0.text', 'Rezervirajte termin unaprijed.'),
        );
});

test('terrain usage rules accept new terrain-specific icons', function () {
    $admin = User::factory()->create();
    attachAdminRoleForTerrainUsageRules($admin);

    $response = $this->actingAs($admin)->patchJson(route('app-settings.terrain-usage-rules.replace'), [
        'rules' => [
            ['icon' => 'droplets', 'text' => 'Prije igre obavezno polijte teren.'],
            ['icon' => 'shovel', 'text' => 'Nakon igre poravnajte teren.'],
            ['icon' => 'wine_off', 'text' => 'Alkohol nije dozvoljen na terenu.', 'emphasis' => 'alert'],
        ],
    ]);

    $response
        ->assertSuccessful()
        ->assertJsonPath('data.terrain_usage_rules.0.icon', 'droplets')
        ->assertJsonPath('data.terrain_usage_rules.1.icon', 'shovel')
        ->assertJsonPath('data.terrain_usage_rules.2.icon', 'wine_off');
});

test('terrain usage rules reject invalid icon', function () {
    $admin = User::factory()->create();
    attachAdminRoleForTerrainUsageRules($admin);

    $response = $this->actingAs($admin)->postJson(route('app-settings.terrain-usage-rules.store'), [
        'icon' => 'invalid_icon',
        'text' => 'Tekst pravila',
    ]);

    $response->assertUnprocessable();
});

test('terrain usage rules reject invalid emphasis', function () {
    $admin = User::factory()->create();
    attachAdminRoleForTerrainUsageRules($admin);

    $response = $this->actingAs($admin)->postJson(route('app-settings.terrain-usage-rules.store'), [
        'icon' => 'info',
        'text' => 'Tekst pravila',
        'emphasis' => 'invalid_emphasis',
    ]);

    $response->assertUnprocessable();
});

test('terrain usage rules return emphasis when set', function () {
    $user = User::factory()->create();

    AppSetting::instance()->update([
        'terrain_usage_rules' => [
            ['icon' => 'alert_triangle', 'text' => 'Vazno pravilo', 'emphasis' => 'warning'],
        ],
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('nav.terrain_usage_rules', 1)
            ->where('nav.terrain_usage_rules.0.emphasis', 'warning'),
        );
});
