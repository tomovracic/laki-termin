<?php

use App\Enums\ReservationSlotStatus;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\ReservationSlot;
use App\Models\Terrain;
use App\Models\TerrainSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard with terrain availability', function () {
    CarbonImmutable::setTestNow('2026-03-04 09:00:00');

    try {
        $user = User::factory()->create(['token_count' => 7]);
        $this->actingAs($user);

        $terrain = Terrain::query()->create([
            'name' => 'Court A',
            'code' => 'court-a',
            'is_active' => true,
        ]);
        TerrainSetting::query()->create([
            'terrain_id' => null,
            'is_global' => true,
            'max_advance_days' => 30,
            'availability_periods' => [
                [
                    'from' => '10:00',
                    'to' => '12:00',
                    'slot_duration_minutes' => 60,
                ],
            ],
        ]);

        ReservationSlot::query()->create([
            'terrain_id' => $terrain->id,
            'starts_at' => now()->setTime(10, 0),
            'ends_at' => now()->setTime(11, 0),
            'status' => ReservationSlotStatus::Available,
        ]);
        ReservationSlot::query()->create([
            'terrain_id' => $terrain->id,
            'starts_at' => now()->setTime(11, 0),
            'ends_at' => now()->setTime(12, 0),
            'status' => ReservationSlotStatus::Reserved,
        ]);

        $response = $this->get(route('dashboard'));
        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('token_count', 7)
                ->where('max_advance_days', 30)
                ->has('terrains', 1)
                ->where('terrains.0.name', 'Court A')
                ->where('terrains.0.available_slots_count', 1)
                ->has('terrains.0.slots', 1),
            );
    } finally {
        CarbonImmutable::setTestNow();
    }
});

test('dashboard availability endpoint returns selected date and token count', function () {
    CarbonImmutable::setTestNow('2026-03-04 09:00:00');

    try {
        $user = User::factory()->create(['token_count' => 3]);
        $this->actingAs($user);

        $terrain = Terrain::query()->create([
            'name' => 'Court B',
            'code' => 'court-b',
            'is_active' => true,
        ]);
        TerrainSetting::query()->create([
            'terrain_id' => null,
            'is_global' => true,
            'max_advance_days' => 30,
            'availability_periods' => [
                [
                    'from' => '09:00',
                    'to' => '10:00',
                    'slot_duration_minutes' => 60,
                ],
            ],
        ]);
        ReservationSlot::query()->create([
            'terrain_id' => $terrain->id,
            'starts_at' => '2026-03-10 09:00:00',
            'ends_at' => '2026-03-10 10:00:00',
            'status' => ReservationSlotStatus::Available,
        ]);

        $this->getJson(route('dashboard.availability', ['date' => '2026-03-10']))
            ->assertOk()
            ->assertJsonPath('data.selected_date', '2026-03-10')
            ->assertJsonPath('data.max_advance_days', 30)
            ->assertJsonPath('data.token_count', 3)
            ->assertJsonPath('data.terrains.0.available_slots_count', 1)
            ->assertJsonPath('data.terrains.0.slots.0.status', ReservationSlotStatus::Available->value);
    } finally {
        CarbonImmutable::setTestNow();
    }
});

test('authenticated users can open dedicated reservations page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $terrain = Terrain::query()->create([
        'name' => 'Court Reservations',
        'code' => 'court-reservations',
        'is_active' => true,
    ]);

    $ownedSlot = ReservationSlot::query()->create([
        'terrain_id' => $terrain->id,
        'starts_at' => now()->addDay()->setTime(15, 0),
        'ends_at' => now()->addDay()->setTime(16, 0),
        'status' => ReservationSlotStatus::Reserved,
    ]);
    $ownedReservation = Reservation::query()->create([
        'user_id' => $user->id,
        'reservation_slot_id' => $ownedSlot->id,
        'status' => ReservationStatus::Confirmed,
        'reserved_for_date' => $ownedSlot->starts_at->toDateString(),
        'reserved_from_time' => $ownedSlot->starts_at->format('H:i:s'),
        'reserved_to_time' => $ownedSlot->ends_at->format('H:i:s'),
    ]);

    $this->get(route('dashboard.reservations'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/reservations')
            ->has('reservations', 1)
            ->where('reservations.0.id', $ownedReservation->id)
            ->where('reservations.0.user_id', $user->id)
            ->where('reservations.0.reserved_for_date', $ownedSlot->starts_at->toDateString())
            ->where('reservations.0.reserved_from_time', '15:00:00')
            ->where('reservations.0.reserved_to_time', '16:00:00'));
});

test('terrain reservation page shows all slot statuses for selected date', function () {
    CarbonImmutable::setTestNow('2026-03-04 09:00:00');

    try {
        $user = User::factory()->create(['token_count' => 5]);
        $this->actingAs($user);
        $slotOwner = User::factory()->create(['name' => 'John Doe']);

        $terrain = Terrain::query()->create([
            'name' => 'Court C',
            'code' => 'court-c',
            'is_active' => true,
        ]);
        TerrainSetting::query()->create([
            'terrain_id' => null,
            'is_global' => true,
            'max_advance_days' => 30,
            'availability_periods' => [
                [
                    'from' => '08:00',
                    'to' => '11:00',
                    'slot_duration_minutes' => 60,
                ],
            ],
        ]);

        ReservationSlot::query()->create([
            'terrain_id' => $terrain->id,
            'starts_at' => '2026-03-12 08:00:00',
            'ends_at' => '2026-03-12 09:00:00',
            'status' => ReservationSlotStatus::Available,
        ]);
        $reservedSlot = ReservationSlot::query()->create([
            'terrain_id' => $terrain->id,
            'starts_at' => '2026-03-12 09:00:00',
            'ends_at' => '2026-03-12 10:00:00',
            'status' => ReservationSlotStatus::Reserved,
        ]);
        ReservationSlot::query()->create([
            'terrain_id' => $terrain->id,
            'starts_at' => '2026-03-12 10:00:00',
            'ends_at' => '2026-03-12 11:00:00',
            'status' => ReservationSlotStatus::Blocked,
        ]);

        Reservation::query()->create([
            'user_id' => $slotOwner->id,
            'reservation_slot_id' => $reservedSlot->id,
            'status' => ReservationStatus::Confirmed,
            'confirmed_at' => now(),
        ]);

        $this->get(route('dashboard.terrains.show', [
            'terrain' => $terrain->id,
            'date' => '2026-03-12',
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('terrains/show')
                ->where('token_count', 5)
                ->where('terrain.name', 'Court C')
                ->has('slots', 3)
                ->where('slots.0.status', ReservationSlotStatus::Available->value)
                ->where('slots.1.status', ReservationSlotStatus::Reserved->value)
                ->where('slots.1.reserved_by.first_name', 'John')
                ->where('slots.1.reserved_by.last_name', 'Doe')
                ->where('slots.2.status', ReservationSlotStatus::Blocked->value),
            );
    } finally {
        CarbonImmutable::setTestNow();
    }
});

test('terrain slots endpoint shows reserved history and marks passed unreserved slots as past', function () {
    CarbonImmutable::setTestNow('2026-03-04 12:00:00');

    try {
        $user = User::factory()->create(['token_count' => 5]);
        $this->actingAs($user);
        $slotOwner = User::factory()->create(['name' => 'Jane Doe']);

        $terrain = Terrain::query()->create([
            'name' => 'Court D',
            'code' => 'court-d',
            'is_active' => true,
        ]);

        TerrainSetting::query()->create([
            'terrain_id' => null,
            'is_global' => true,
            'max_advance_days' => 30,
            'availability_periods' => [
                [
                    'from' => '10:00',
                    'to' => '14:00',
                    'slot_duration_minutes' => 60,
                ],
            ],
        ]);

        $reservedSlot = ReservationSlot::query()->create([
            'terrain_id' => $terrain->id,
            'starts_at' => '2026-03-04 11:00:00',
            'ends_at' => '2026-03-04 12:00:00',
            'status' => ReservationSlotStatus::Reserved,
        ]);
        Reservation::query()->create([
            'user_id' => $slotOwner->id,
            'reservation_slot_id' => $reservedSlot->id,
            'status' => ReservationStatus::Confirmed,
            'confirmed_at' => now(),
        ]);

        $response = $this->getJson(route('dashboard.terrains.slots', [
            'terrain' => $terrain->id,
            'date' => '2026-03-04',
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('data.selected_date', '2026-03-04')
            ->assertJsonCount(4, 'data.slots');

        $startTimes = collect($response->json('data.slots'))
            ->pluck('starts_at')
            ->map(static fn (string $startsAt): string => CarbonImmutable::parse($startsAt)->format('H:i'))
            ->values()
            ->all();

        $statuses = collect($response->json('data.slots'))
            ->pluck('status')
            ->values()
            ->all();

        expect($startTimes)->toBe(['10:00', '11:00', '12:00', '13:00']);
        expect($statuses)->toBe([
            ReservationSlotStatus::Past->value,
            ReservationSlotStatus::Reserved->value,
            ReservationSlotStatus::Available->value,
            ReservationSlotStatus::Available->value,
        ]);
        expect($response->json('data.slots.1.reserved_by.first_name'))->toBe('Jane');
        expect($response->json('data.slots.1.reserved_by.last_name'))->toBe('Doe');
    } finally {
        CarbonImmutable::setTestNow();
    }
});

test('terrain slots endpoint clamps selected date to max advance days', function () {
    CarbonImmutable::setTestNow('2026-03-04 09:00:00');

    try {
        $user = User::factory()->create(['token_count' => 5]);
        $this->actingAs($user);

        $terrain = Terrain::query()->create([
            'name' => 'Court E',
            'code' => 'court-e',
            'is_active' => true,
        ]);
        TerrainSetting::query()->create([
            'terrain_id' => null,
            'is_global' => true,
            'max_advance_days' => 2,
            'availability_periods' => [
                [
                    'from' => '08:00',
                    'to' => '10:00',
                    'slot_duration_minutes' => 60,
                ],
            ],
        ]);

        $this->getJson(route('dashboard.terrains.slots', [
            'terrain' => $terrain->id,
            'date' => '2026-03-20',
        ]))
            ->assertOk()
            ->assertJsonPath('data.selected_date', '2026-03-06')
            ->assertJsonPath('data.max_advance_days', 2);
    } finally {
        CarbonImmutable::setTestNow();
    }
});
