<?php

use App\Enums\InactivePeriodReason;
use App\Enums\ReservationSlotStatus;
use App\Models\ReservationSlot;
use App\Models\Role;
use App\Models\Terrain;
use App\Models\TerrainInactivePeriod;
use App\Models\TerrainSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

function assignInactivePeriodAdminRole(User $user): void
{
    $role = Role::query()->firstOrCreate(['name' => 'admin']);
    $user->roles()->syncWithoutDetaching([$role->id]);
}

test('admin can create time-range blocked period for part of a day', function () {
    $admin = User::factory()->create();
    assignInactivePeriodAdminRole($admin);

    $blockedDate = now()->addDay()->toDateString();

    $response = $this->actingAs($admin)->postJson(route('terrain-inactive-periods.store'), [
        'block_type' => 'time_range',
        'from_date' => $blockedDate,
        'to_date' => $blockedDate,
        'from_time' => '20:00',
        'to_time' => '23:00',
        'terrain_id' => null,
        'reason' => InactivePeriodReason::Other->value,
        'note' => 'Evening event',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.block_type', 'time_range')
        ->assertJsonPath('data.from_date', $blockedDate)
        ->assertJsonPath('data.to_date', $blockedDate)
        ->assertJsonPath('data.from_time', '20:00')
        ->assertJsonPath('data.to_time', '23:00')
        ->assertJsonPath('data.reason', 'other')
        ->assertJsonPath('data.note', 'Evening event');

    $period = TerrainInactivePeriod::query()->first();

    expect($period)->not->toBeNull();
    expect($period->from_at->format('Y-m-d H:i:s'))->toBe(
        CarbonImmutable::createFromFormat('Y-m-d H:i', "{$blockedDate} 20:00", 'Europe/Zagreb')->toDateTimeString(),
    );
    expect($period->to_at->format('Y-m-d H:i:s'))->toBe(
        CarbonImmutable::createFromFormat('Y-m-d H:i', "{$blockedDate} 23:00", 'Europe/Zagreb')->toDateTimeString(),
    );
});

test('time-range blocked period requires end time after start time', function () {
    $admin = User::factory()->create();
    assignInactivePeriodAdminRole($admin);

    $blockedDate = now()->addDay()->toDateString();

    $response = $this->actingAs($admin)->postJson(route('terrain-inactive-periods.store'), [
        'block_type' => 'time_range',
        'from_date' => $blockedDate,
        'to_date' => $blockedDate,
        'from_time' => '22:00',
        'to_time' => '20:00',
        'reason' => InactivePeriodReason::Other->value,
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['to_time']);
});

test('slots endpoint returns blocked status only for slots overlapping time-range period', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 7, 3, 10, 0, 0, 'Europe/Zagreb'));

    try {
        $user = User::factory()->create();
        $admin = User::factory()->create();
        assignInactivePeriodAdminRole($admin);

        TerrainSetting::query()->create([
            'terrain_id' => null,
            'is_global' => true,
            'max_advance_days' => 30,
            'availability_periods' => [
                [
                    'from' => '08:00',
                    'to' => '22:00',
                    'slot_duration_minutes' => 60,
                ],
            ],
        ]);

        $terrain = Terrain::query()->create([
            'name' => 'Court Partial',
            'code' => 'court-partial',
            'is_active' => true,
        ]);

        $morningSlot = ReservationSlot::query()->create([
            'terrain_id' => $terrain->id,
            'starts_at' => '2026-07-05 10:00:00',
            'ends_at' => '2026-07-05 11:00:00',
            'status' => ReservationSlotStatus::Available,
        ]);
        $eveningSlot = ReservationSlot::query()->create([
            'terrain_id' => $terrain->id,
            'starts_at' => '2026-07-05 20:00:00',
            'ends_at' => '2026-07-05 21:00:00',
            'status' => ReservationSlotStatus::Available,
        ]);

        TerrainInactivePeriod::query()->create([
            'terrain_id' => null,
            'created_by' => $admin->id,
            'from_at' => '2026-07-05 20:00:00',
            'to_at' => '2026-07-05 23:00:00',
            'reason' => InactivePeriodReason::Other->value,
        ]);

        $response = $this->actingAs($user)->getJson(route('dashboard.terrains.slots', [
            'terrain' => $terrain->id,
            'date' => '2026-07-05',
        ]));

        $response->assertOk();

        $slots = collect($response->json('data.slots'));

        expect($slots->firstWhere('id', $morningSlot->id)['status'])
            ->toBe(ReservationSlotStatus::Available->value);
        expect($slots->firstWhere('id', $eveningSlot->id)['status'])
            ->toBe(ReservationSlotStatus::Blocked->value);
    } finally {
        CarbonImmutable::setTestNow();
    }
});

test('admin can create global blocked day using from_date and to_date', function () {
    $admin = User::factory()->create();
    assignInactivePeriodAdminRole($admin);

    $fromDate = now()->addDays(3)->toDateString();
    $toDate = now()->addDays(5)->toDateString();

    $response = $this->actingAs($admin)->postJson(route('terrain-inactive-periods.store'), [
        'from_date' => $fromDate,
        'to_date' => $toDate,
        'terrain_id' => null,
        'reason' => InactivePeriodReason::Rain->value,
        'note' => 'Heavy rain expected',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.terrain_id', null)
        ->assertJsonPath('data.from_date', $fromDate)
        ->assertJsonPath('data.to_date', $toDate)
        ->assertJsonPath('data.reason', 'rain')
        ->assertJsonPath('data.note', 'Heavy rain expected');

    expect(TerrainInactivePeriod::query()->count())->toBe(1);
});

test('admin can create per-terrain blocked day', function () {
    $admin = User::factory()->create();
    assignInactivePeriodAdminRole($admin);

    $terrain = Terrain::query()->create([
        'name' => 'Court Rain',
        'code' => 'court-rain',
        'is_active' => true,
    ]);

    $blockedDate = now()->addDay()->toDateString();

    $response = $this->actingAs($admin)->postJson(route('terrain-inactive-periods.store'), [
        'from_date' => $blockedDate,
        'to_date' => $blockedDate,
        'terrain_id' => $terrain->id,
        'reason' => InactivePeriodReason::Maintenance->value,
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.terrain_id', $terrain->id)
        ->assertJsonPath('data.terrain_name', 'Court Rain')
        ->assertJsonPath('data.from_date', $blockedDate)
        ->assertJsonPath('data.to_date', $blockedDate)
        ->assertJsonPath('data.reason', 'maintenance');
});

test('non-admin cannot create blocked day', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('terrain-inactive-periods.store'), [
        'from_date' => now()->addDay()->toDateString(),
        'to_date' => now()->addDay()->toDateString(),
        'reason' => InactivePeriodReason::Rain->value,
    ]);

    $response->assertForbidden();
});

test('admin can delete blocked day', function () {
    $admin = User::factory()->create();
    assignInactivePeriodAdminRole($admin);

    $period = TerrainInactivePeriod::query()->create([
        'terrain_id' => null,
        'created_by' => $admin->id,
        'from_at' => now()->addDay()->startOfDay(),
        'to_at' => now()->addDay()->endOfDay(),
        'reason' => InactivePeriodReason::Rain->value,
    ]);

    $response = $this->actingAs($admin)->deleteJson(
        route('terrain-inactive-periods.destroy', $period),
    );

    $response
        ->assertOk()
        ->assertJsonPath('data.deleted', true);

    expect(TerrainInactivePeriod::query()->count())->toBe(0);
});

test('admin terrains page includes inactive periods', function () {
    $admin = User::factory()->create();
    assignInactivePeriodAdminRole($admin);

    $blockedDate = now()->addDays(2)->toDateString();

    TerrainInactivePeriod::query()->create([
        'terrain_id' => null,
        'created_by' => $admin->id,
        'from_at' => CarbonImmutable::createFromFormat('Y-m-d', $blockedDate, 'Europe/Zagreb')->startOfDay(),
        'to_at' => CarbonImmutable::createFromFormat('Y-m-d', $blockedDate, 'Europe/Zagreb')->endOfDay(),
        'reason' => InactivePeriodReason::Rain->value,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.terrains'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/terrains')
            ->has('inactive_periods', 1)
            ->where('inactive_periods.0.from_date', $blockedDate)
            ->where('inactive_periods.0.reason', 'rain'),
        );
});

test('slots endpoint returns blocked status when slot overlaps inactive period', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 7, 3, 10, 0, 0, 'Europe/Zagreb'));

    try {
        $user = User::factory()->create();
        $admin = User::factory()->create();
        assignInactivePeriodAdminRole($admin);

        TerrainSetting::query()->create([
            'terrain_id' => null,
            'is_global' => true,
            'max_advance_days' => 30,
            'availability_periods' => [
                [
                    'from' => '08:00',
                    'to' => '22:00',
                    'slot_duration_minutes' => 60,
                ],
            ],
        ]);

        $terrain = Terrain::query()->create([
            'name' => 'Court Blocked',
            'code' => 'court-blocked',
            'is_active' => true,
        ]);

        $slot = ReservationSlot::query()->create([
            'terrain_id' => $terrain->id,
            'starts_at' => '2026-07-05 10:00:00',
            'ends_at' => '2026-07-05 11:00:00',
            'status' => ReservationSlotStatus::Available,
        ]);

        TerrainInactivePeriod::query()->create([
            'terrain_id' => null,
            'created_by' => $admin->id,
            'from_at' => '2026-07-05 00:00:00',
            'to_at' => '2026-07-05 23:59:59',
            'reason' => InactivePeriodReason::Rain->value,
        ]);

        $response = $this->actingAs($user)->getJson(route('dashboard.terrains.slots', [
            'terrain' => $terrain->id,
            'date' => '2026-07-05',
        ]));

        $response->assertOk();

        $slotPayload = collect($response->json('data.slots'))->firstWhere('id', $slot->id);

        expect($slotPayload)->not->toBeNull();
        expect($slotPayload['status'])->toBe(ReservationSlotStatus::Blocked->value);
        expect($slot->fresh()?->status)->toBe(ReservationSlotStatus::Available);
    } finally {
        CarbonImmutable::setTestNow();
    }
});
