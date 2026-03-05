<?php

use App\Enums\ReservationSlotStatus;
use App\Enums\ReservationStatus;
use App\Models\Permission;
use App\Models\Reservation;
use App\Models\ReservationSlot;
use App\Models\Role;
use App\Models\Terrain;
use App\Models\TerrainInactivePeriod;
use App\Models\TerrainSetting;
use App\Models\User;

function grantReservationCreatePermission(User $user): void
{
    $role = Role::query()->firstOrCreate(['name' => 'member']);
    $permission = Permission::query()->firstOrCreate(['name' => 'reservation.create']);
    $role->permissions()->syncWithoutDetaching([$permission->id]);
    $user->roles()->syncWithoutDetaching([$role->id]);
}

test('user can create reservation and receives reservation tokens', function () {
    $user = User::factory()->create();
    grantReservationCreatePermission($user);

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
        'name' => 'Court A',
        'code' => 'court-a',
        'is_active' => true,
    ]);

    $slotDay = now()->addDay();

    $slot = ReservationSlot::query()->create([
        'terrain_id' => $terrain->id,
        'starts_at' => $slotDay->copy()->setTime(10, 0),
        'ends_at' => $slotDay->copy()->setTime(11, 0),
        'status' => ReservationSlotStatus::Available,
    ]);

    $response = $this->actingAs($user)->post(route('reservations.store'), [
        'reservation_slot_id' => $slot->id,
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending');

    expect(Reservation::query()->count())->toBe(1);
    expect(Reservation::query()->first()->tokens()->count())->toBe(2);
});

test('user can bulk reserve slots and slots endpoint returns reserving user name', function () {
    $user = User::factory()->create([
        'first_name' => 'Marko',
        'last_name' => 'Maric',
        'token_count' => 5,
    ]);
    grantReservationCreatePermission($user);

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
        'name' => 'Court Bulk',
        'code' => 'court-bulk',
        'is_active' => true,
    ]);

    $slotDay = now()->addDay();

    $firstSlot = ReservationSlot::query()->create([
        'terrain_id' => $terrain->id,
        'starts_at' => $slotDay->copy()->setTime(10, 0),
        'ends_at' => $slotDay->copy()->setTime(11, 0),
        'status' => ReservationSlotStatus::Available,
    ]);
    $secondSlot = ReservationSlot::query()->create([
        'terrain_id' => $terrain->id,
        'starts_at' => $slotDay->copy()->setTime(11, 0),
        'ends_at' => $slotDay->copy()->setTime(12, 0),
        'status' => ReservationSlotStatus::Available,
    ]);

    $this->actingAs($user)
        ->postJson(route('reservations.bulk-store'), [
            'reservation_slot_ids' => [$firstSlot->id, $secondSlot->id],
        ])
        ->assertCreated()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.tokens_remaining', 3);

    $slotsResponse = $this->actingAs($user)->getJson(route('dashboard.terrains.slots', [
        'terrain' => $terrain->id,
        'date' => $slotDay->toDateString(),
    ]));

    $slotsResponse->assertOk();

    $slots = collect($slotsResponse->json('data.slots'));
    $firstReservedSlot = $slots->firstWhere('id', $firstSlot->id);
    $secondReservedSlot = $slots->firstWhere('id', $secondSlot->id);

    expect($firstReservedSlot['status'])->toBe(ReservationSlotStatus::Reserved->value);
    expect($firstReservedSlot['reserved_by']['first_name'])->toBe('Marko');
    expect($firstReservedSlot['reserved_by']['last_name'])->toBe('Maric');
    expect($secondReservedSlot['status'])->toBe(ReservationSlotStatus::Reserved->value);
    expect($secondReservedSlot['reserved_by']['first_name'])->toBe('Marko');
    expect($secondReservedSlot['reserved_by']['last_name'])->toBe('Maric');
});

test('user can reserve the same slot again after cancellation', function () {
    $user = User::factory()->create([
        'token_count' => 2,
    ]);
    grantReservationCreatePermission($user);

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
        'name' => 'Court Replay',
        'code' => 'court-replay',
        'is_active' => true,
    ]);

    $slotDay = now()->addDay();

    $slot = ReservationSlot::query()->create([
        'terrain_id' => $terrain->id,
        'starts_at' => $slotDay->copy()->setTime(13, 0),
        'ends_at' => $slotDay->copy()->setTime(14, 0),
        'status' => ReservationSlotStatus::Available,
    ]);

    $firstReservationResponse = $this->actingAs($user)
        ->postJson(route('reservations.bulk-store'), [
            'reservation_slot_ids' => [$slot->id],
        ])
        ->assertCreated()
        ->assertJsonPath('meta.tokens_remaining', 1);

    $firstReservationId = (int) $firstReservationResponse->json('data.0.id');

    $this->actingAs($user)
        ->post(route('reservations.cancel', $firstReservationId), [
            'cancel_reason' => 'plans changed',
        ])
        ->assertOk();

    $this->actingAs($user)
        ->postJson(route('reservations.bulk-store'), [
            'reservation_slot_ids' => [$slot->id],
        ])
        ->assertCreated()
        ->assertJsonPath('meta.tokens_remaining', 1);

    expect(Reservation::query()->where('reservation_slot_id', $slot->id)->count())->toBe(2);
    expect($slot->fresh()?->status)->toBe(ReservationSlotStatus::Reserved);
});

test('reservation fails when slot overlaps inactive terrain period', function () {
    $user = User::factory()->create();
    grantReservationCreatePermission($user);

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
        'name' => 'Court B',
        'code' => 'court-b',
        'is_active' => true,
    ]);

    $slotStart = now()->addDay()->setTime(12, 0);
    $slotEnd = now()->addDay()->setTime(13, 0);

    ReservationSlot::query()->create([
        'terrain_id' => $terrain->id,
        'starts_at' => $slotStart,
        'ends_at' => $slotEnd,
        'status' => ReservationSlotStatus::Available,
    ]);

    TerrainInactivePeriod::query()->create([
        'terrain_id' => $terrain->id,
        'created_by' => $user->id,
        'from_at' => $slotStart->copy()->subMinutes(10),
        'to_at' => $slotEnd->copy()->addMinutes(10),
        'reason' => 'rain',
    ]);

    $response = $this->actingAs($user)->post(route('reservations.store'), [
        'reservation_slot_id' => ReservationSlot::query()->first()->id,
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('errors.reservation.0', 'Selected slot overlaps an inactive terrain period.');
});

test('user cannot cancel reservation owned by another user', function () {
    $owner = User::factory()->create();
    grantReservationCreatePermission($owner);
    $otherUser = User::factory()->create();

    $terrain = Terrain::query()->create([
        'name' => 'Court C',
        'code' => 'court-c',
        'is_active' => true,
    ]);

    $slot = ReservationSlot::query()->create([
        'terrain_id' => $terrain->id,
        'starts_at' => now()->addDay()->setTime(15, 0),
        'ends_at' => now()->addDay()->setTime(16, 0),
        'status' => ReservationSlotStatus::Reserved,
    ]);

    $reservation = Reservation::query()->create([
        'user_id' => $owner->id,
        'reservation_slot_id' => $slot->id,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($otherUser)->post(route('reservations.cancel', $reservation), [
        'cancel_reason' => 'not mine',
    ]);

    $response->assertForbidden();
});

test('user cannot cancel own reservation when slot already started', function () {
    $user = User::factory()->create();
    grantReservationCreatePermission($user);

    $terrain = Terrain::query()->create([
        'name' => 'Court D',
        'code' => 'court-d',
        'is_active' => true,
    ]);

    $slot = ReservationSlot::query()->create([
        'terrain_id' => $terrain->id,
        'starts_at' => now()->subMinutes(10),
        'ends_at' => now()->addMinutes(50),
        'status' => ReservationSlotStatus::Reserved,
    ]);

    $reservation = Reservation::query()->create([
        'user_id' => $user->id,
        'reservation_slot_id' => $slot->id,
        'status' => ReservationStatus::Pending,
    ]);

    $response = $this->actingAs($user)->post(route('reservations.cancel', $reservation), [
        'cancel_reason' => 'late',
    ]);

    $response
        ->assertStatus(422)
        ->assertJsonPath('errors.reservation.0', 'Cannot cancel a reservation for a slot that has already started.');

    expect($reservation->fresh()?->status)->toBe(ReservationStatus::Pending);
    expect($slot->fresh()?->status)->toBe(ReservationSlotStatus::Reserved);
});

test('cancelling reservation returns one token to user', function () {
    $user = User::factory()->create([
        'token_count' => 1,
    ]);
    grantReservationCreatePermission($user);

    $terrain = Terrain::query()->create([
        'name' => 'Court E',
        'code' => 'court-e',
        'is_active' => true,
    ]);

    $slot = ReservationSlot::query()->create([
        'terrain_id' => $terrain->id,
        'starts_at' => now()->addDay()->setTime(17, 0),
        'ends_at' => now()->addDay()->setTime(18, 0),
        'status' => ReservationSlotStatus::Reserved,
    ]);

    $reservation = Reservation::query()->create([
        'user_id' => $user->id,
        'reservation_slot_id' => $slot->id,
        'status' => ReservationStatus::Pending,
    ]);

    $this->actingAs($user)->post(route('reservations.cancel', $reservation), [
        'cancel_reason' => 'plans changed',
    ])->assertOk();

    expect($user->fresh()?->token_count)->toBe(2);
    expect($reservation->fresh()?->status)->toBe(ReservationStatus::Cancelled);
    expect($slot->fresh()?->status)->toBe(ReservationSlotStatus::Available);
});
