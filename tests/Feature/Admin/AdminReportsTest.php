<?php

use App\Enums\ReservationSlotStatus;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\ReservationSlot;
use App\Models\Role;
use App\Models\Terrain;
use App\Models\User;
use App\Models\UserLoginLog;
use Illuminate\Auth\Events\Login;
use Inertia\Testing\AssertableInertia as Assert;

function assignAdminRoleForReports(User $user): void
{
    $role = Role::query()->firstOrCreate(['name' => 'admin']);
    $user->roles()->syncWithoutDetaching([$role->id]);
}

test('admin can open reports overview and report pages', function () {
    $admin = User::factory()->create();
    assignAdminRoleForReports($admin);

    $this->actingAs($admin)
        ->get(route('admin.reports'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/index')
            ->has('stats.reserved_count')
            ->has('stats.cancelled_count'),
        );

    $this->actingAs($admin)
        ->get(route('admin.reports.logins'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/logins')
            ->has('logs.data')
            ->has('users')
            ->has('filters'),
        );

    $this->actingAs($admin)
        ->get(route('admin.reports.reserved'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/reserved')
            ->has('reservations.data')
            ->has('terrains')
        );

    $this->actingAs($admin)
        ->get(route('admin.reports.cancelled'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/cancelled')
            ->has('reservations.data'),
        );
});

test('non-admin cannot open admin reports', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.reports'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('admin.reports.logins'))
        ->assertForbidden();
});

test('login event records a single user login log', function () {
    $user = User::factory()->create();

    event(new Login('web', $user, false));

    expect(UserLoginLog::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('admin login report filters by user and search', function () {
    $admin = User::factory()->create(['first_name' => 'Admin', 'last_name' => 'User']);
    assignAdminRoleForReports($admin);

    $targetUser = User::factory()->create(['first_name' => 'Ana', 'last_name' => 'Anic']);
    $otherUser = User::factory()->create(['first_name' => 'Ivo', 'last_name' => 'Ivic']);

    UserLoginLog::query()->create([
        'user_id' => $targetUser->id,
        'logged_in_at' => now()->subDay(),
    ]);
    UserLoginLog::query()->create([
        'user_id' => $otherUser->id,
        'logged_in_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.reports.logins', ['user_id' => $targetUser->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.user_id', $targetUser->id),
        );

    $this->actingAs($admin)
        ->get(route('admin.reports.logins', ['search' => 'Anic']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.user.first_name', 'Ana'),
        );
});

test('admin reserved report shows only active reservations with filters', function () {
    $admin = User::factory()->create();
    assignAdminRoleForReports($admin);

    $targetUser = User::factory()->create();
    $otherUser = User::factory()->create();

    $terrain = Terrain::query()->create([
        'name' => 'Court Reports',
        'description' => 'Test terrain',
        'code' => 'court-reports',
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

    $activeReservation = Reservation::query()->create([
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
        'status' => ReservationStatus::Cancelled,
        'reserved_for_date' => $otherSlot->starts_at->toDateString(),
        'reserved_from_time' => $otherSlot->starts_at->format('H:i:s'),
        'reserved_to_time' => $otherSlot->ends_at->format('H:i:s'),
        'cancelled_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.reports.reserved', ['user_id' => $targetUser->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('reservations.data', 1)
            ->where('reservations.data.0.id', $activeReservation->id),
        );
});

test('admin cancelled report shows only cancelled reservations', function () {
    $admin = User::factory()->create();
    assignAdminRoleForReports($admin);

    $user = User::factory()->create();
    $terrain = Terrain::query()->create([
        'name' => 'Court Cancelled',
        'description' => 'Test terrain',
        'code' => 'court-cancelled',
        'is_active' => true,
    ]);

    $slot = ReservationSlot::query()->create([
        'terrain_id' => $terrain->id,
        'starts_at' => now()->addDay()->setTime(9, 0),
        'ends_at' => now()->addDay()->setTime(10, 0),
        'status' => ReservationSlotStatus::Available,
    ]);

    $cancelledReservation = Reservation::query()->create([
        'user_id' => $user->id,
        'reservation_slot_id' => $slot->id,
        'status' => ReservationStatus::Cancelled,
        'reserved_for_date' => $slot->starts_at->toDateString(),
        'reserved_from_time' => $slot->starts_at->format('H:i:s'),
        'reserved_to_time' => $slot->ends_at->format('H:i:s'),
        'cancelled_at' => now()->subHours(2),
        'cancel_reason' => 'Rain',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.reports.cancelled'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('reservations.data', 1)
            ->where('reservations.data.0.id', $cancelledReservation->id)
            ->where('reservations.data.0.cancel_reason', 'Rain'),
        );
});
