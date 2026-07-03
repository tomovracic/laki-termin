<?php

use App\Models\AppSetting;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('user who has not acknowledged rules must acknowledge when rules exist', function () {
    $user = User::factory()->create([
        'terrain_usage_rules_acknowledged_at' => null,
    ]);

    AppSetting::instance()->update([
        'terrain_usage_rules' => [
            ['icon' => 'coins', 'text' => 'Jedna rezervacija koristi jedan token.'],
        ],
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('nav.must_acknowledge_terrain_usage_rules', true),
        );
});

test('user who acknowledged rules does not need to acknowledge again', function () {
    $user = User::factory()->create([
        'terrain_usage_rules_acknowledged_at' => now(),
    ]);

    AppSetting::instance()->update([
        'terrain_usage_rules' => [
            ['icon' => 'coins', 'text' => 'Jedna rezervacija koristi jedan token.'],
        ],
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('nav.must_acknowledge_terrain_usage_rules', false),
        );
});

test('user does not need to acknowledge when no rules exist', function () {
    $user = User::factory()->create([
        'terrain_usage_rules_acknowledged_at' => null,
    ]);

    AppSetting::instance()->update([
        'terrain_usage_rules' => null,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('nav.must_acknowledge_terrain_usage_rules', false),
        );
});

test('user can acknowledge terrain usage rules', function () {
    $user = User::factory()->create([
        'terrain_usage_rules_acknowledged_at' => null,
    ]);

    AppSetting::instance()->update([
        'terrain_usage_rules' => [
            ['icon' => 'info', 'text' => 'Procitajte pravila prije rezervacije.'],
        ],
    ]);

    $this->actingAs($user)
        ->post(route('terrain-usage-rules.acknowledge'))
        ->assertRedirect();

    expect($user->refresh()->terrain_usage_rules_acknowledged_at)->not->toBeNull();
});

test('acknowledging terrain usage rules is idempotent', function () {
    $acknowledgedAt = now()->subDay();

    $user = User::factory()->create([
        'terrain_usage_rules_acknowledged_at' => $acknowledgedAt,
    ]);

    $this->actingAs($user)
        ->post(route('terrain-usage-rules.acknowledge'))
        ->assertRedirect();

    expect($user->refresh()->terrain_usage_rules_acknowledged_at?->toDateTimeString())
        ->toBe($acknowledgedAt->toDateTimeString());
});

test('after acknowledging rules nav flag is false', function () {
    $user = User::factory()->create([
        'terrain_usage_rules_acknowledged_at' => null,
    ]);

    AppSetting::instance()->update([
        'terrain_usage_rules' => [
            ['icon' => 'ban', 'text' => 'Zabranjeno je ostavljati smece na terenu.'],
        ],
    ]);

    $this->actingAs($user)
        ->post(route('terrain-usage-rules.acknowledge'))
        ->assertRedirect();

    $this->actingAs($user->refresh())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('nav.must_acknowledge_terrain_usage_rules', false),
        );
});

test('guest cannot acknowledge terrain usage rules', function () {
    $this->post(route('terrain-usage-rules.acknowledge'))
        ->assertRedirect(route('login'));
});
