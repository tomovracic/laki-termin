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

test('admin can save terrain usage rules', function () {
    $admin = User::factory()->create();
    attachAdminRoleForTerrainUsageRules($admin);

    $response = $this->actingAs($admin)->patchJson(route('app-settings.terrain-usage-rules.update'), [
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

test('admin can clear terrain usage rules', function () {
    $admin = User::factory()->create();
    attachAdminRoleForTerrainUsageRules($admin);

    AppSetting::instance()->update([
        'terrain_usage_rules' => [
            ['icon' => 'info', 'text' => 'Staro pravilo'],
        ],
    ]);

    $response = $this->actingAs($admin)->patchJson(route('app-settings.terrain-usage-rules.update'), [
        'rules' => [],
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.terrain_usage_rules', []);

    expect(AppSetting::instance()->terrain_usage_rules)->toBeNull();
});

test('non-admin cannot update terrain usage rules', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->patchJson(route('app-settings.terrain-usage-rules.update'), [
        'rules' => [
            ['icon' => 'info', 'text' => 'Neovlasteno pravilo'],
        ],
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

    $response = $this->actingAs($admin)->patchJson(route('app-settings.terrain-usage-rules.update'), [
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

    $response = $this->actingAs($admin)->patchJson(route('app-settings.terrain-usage-rules.update'), [
        'rules' => [
            ['icon' => 'invalid_icon', 'text' => 'Tekst pravila'],
        ],
    ]);

    $response->assertUnprocessable();
});

test('terrain usage rules reject invalid emphasis', function () {
    $admin = User::factory()->create();
    attachAdminRoleForTerrainUsageRules($admin);

    $response = $this->actingAs($admin)->patchJson(route('app-settings.terrain-usage-rules.update'), [
        'rules' => [
            ['icon' => 'info', 'text' => 'Tekst pravila', 'emphasis' => 'invalid_emphasis'],
        ],
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
