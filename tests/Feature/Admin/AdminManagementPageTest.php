<?php

use App\Models\Role;
use App\Models\Reservation;
use App\Models\ReservationSlot;
use App\Models\Terrain;
use App\Models\TerrainSetting;
use App\Models\User;
use App\Enums\ReservationSlotStatus;
use App\Enums\ReservationStatus;
use Inertia\Testing\AssertableInertia as Assert;

function assignAdminRole(User $user): void
{
    $role = Role::query()->firstOrCreate(['name' => 'admin']);
    $user->roles()->syncWithoutDetaching([$role->id]);
}

test('admin can open users and terrains admin overviews', function () {
    $admin = User::factory()->create(['token_count' => 100]);
    assignAdminRole($admin);

    User::factory()->create(['name' => 'User One', 'token_count' => 5]);
    Terrain::query()->create([
        'name' => 'Court A',
        'description' => 'Outdoor court',
        'code' => 'court-a',
        'is_active' => true,
    ]);
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

    $this->actingAs($admin)
        ->get(route('admin.users'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users')
            ->has('users', 2)
            ->where('users.0.invitation_status', 'active')
            ->where('users.1.invitation_status', 'active')
            ->where('users.0.reservations_count', 0)
            ->where('users.1.reservations_count', 0)
        );

    $this->actingAs($admin)
        ->get(route('admin.terrains'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/terrains')
            ->has('terrains', 1)
            ->where('global_setting.max_advance_days', 30),
        );
});

test('non-admin cannot open admin overviews', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.users'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('admin.terrains'))
        ->assertForbidden();
});

test('admin can view paginated reservations for selected user', function () {
    $admin = User::factory()->create();
    assignAdminRole($admin);

    $targetUser = User::factory()->create();
    $otherUser = User::factory()->create();

    $terrain = Terrain::query()->create([
        'name' => 'Court Admin Reservations',
        'description' => 'Test terrain',
        'code' => 'court-admin-reservations',
        'is_active' => true,
    ]);

    $targetSlot = ReservationSlot::query()->create([
        'terrain_id' => $terrain->id,
        'starts_at' => now()->addDay()->setTime(9, 0),
        'ends_at' => now()->addDay()->setTime(10, 0),
        'status' => ReservationSlotStatus::Reserved,
    ]);
    $otherSlot = ReservationSlot::query()->create([
        'terrain_id' => $terrain->id,
        'starts_at' => now()->addDays(2)->setTime(9, 0),
        'ends_at' => now()->addDays(2)->setTime(10, 0),
        'status' => ReservationSlotStatus::Reserved,
    ]);

    $targetReservation = Reservation::query()->create([
        'user_id' => $targetUser->id,
        'reservation_slot_id' => $targetSlot->id,
        'status' => ReservationStatus::Pending,
        'reserved_for_date' => $targetSlot->starts_at->toDateString(),
        'reserved_from_time' => $targetSlot->starts_at->format('H:i:s'),
        'reserved_to_time' => $targetSlot->ends_at->format('H:i:s'),
    ]);
    Reservation::query()->create([
        'user_id' => $otherUser->id,
        'reservation_slot_id' => $otherSlot->id,
        'status' => ReservationStatus::Pending,
        'reserved_for_date' => $otherSlot->starts_at->toDateString(),
        'reserved_from_time' => $otherSlot->starts_at->format('H:i:s'),
        'reserved_to_time' => $otherSlot->ends_at->format('H:i:s'),
    ]);

    $this->actingAs($admin)
        ->getJson(route('admin.users.reservations', $targetUser))
        ->assertOk()
        ->assertJsonPath('data.0.id', $targetReservation->id)
        ->assertJsonPath('data.0.user_id', $targetUser->id)
        ->assertJsonPath('meta.total', 1);
});

test('non-admin cannot view user reservations from admin endpoint', function () {
    $user = User::factory()->create();
    $targetUser = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('admin.users.reservations', $targetUser))
        ->assertForbidden();
});
