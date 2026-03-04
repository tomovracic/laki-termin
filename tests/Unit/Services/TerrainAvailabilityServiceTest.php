<?php

use App\Enums\ReservationSlotStatus;
use App\Models\ReservationSlot;
use App\Models\Terrain;
use App\Models\TerrainInactivePeriod;
use App\Models\TerrainSetting;
use App\Models\User;
use App\Services\TerrainAvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('service throws when no settings are configured', function () {
    $terrain = Terrain::query()->create([
        'name' => 'No Setting Court',
        'code' => 'no-setting-court',
        'is_active' => true,
    ]);

    $slot = ReservationSlot::query()->create([
        'terrain_id' => $terrain->id,
        'starts_at' => now()->addDay()->setTime(10, 0),
        'ends_at' => now()->addDay()->setTime(11, 0),
        'status' => ReservationSlotStatus::Available,
    ]);

    $service = app(TerrainAvailabilityService::class);

    $service->assertSlotCanBeReserved($slot);
})->throws(DomainException::class, 'Reservation settings are not configured.');

test('service ignores terrain-specific settings and requires global setting', function () {
    $terrain = Terrain::query()->create([
        'name' => 'Terrain Only Setting Court',
        'code' => 'terrain-only-setting-court',
        'is_active' => true,
    ]);

    TerrainSetting::query()->create([
        'terrain_id' => $terrain->id,
        'is_global' => false,
        'max_advance_days' => 30,
        'availability_periods' => [
            [
                'from' => '08:00',
                'to' => '22:00',
                'slot_duration_minutes' => 60,
            ],
        ],
    ]);

    $slot = ReservationSlot::query()->create([
        'terrain_id' => $terrain->id,
        'starts_at' => now()->addDay()->setTime(10, 0),
        'ends_at' => now()->addDay()->setTime(11, 0),
        'status' => ReservationSlotStatus::Available,
    ]);

    $service = app(TerrainAvailabilityService::class);

    $service->assertSlotCanBeReserved($slot);
})->throws(DomainException::class, 'Reservation settings are not configured.');

test('service throws when slot overlaps global inactive period', function () {
    $admin = User::factory()->create();
    $terrain = Terrain::query()->create([
        'name' => 'Global Inactive Court',
        'code' => 'global-inactive-court',
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

    $slot = ReservationSlot::query()->create([
        'terrain_id' => $terrain->id,
        'starts_at' => now()->addDay()->setTime(14, 0),
        'ends_at' => now()->addDay()->setTime(15, 0),
        'status' => ReservationSlotStatus::Available,
    ]);

    TerrainInactivePeriod::query()->create([
        'terrain_id' => null,
        'created_by' => $admin->id,
        'from_at' => now()->addDay()->setTime(13, 0),
        'to_at' => now()->addDay()->setTime(16, 0),
        'reason' => 'maintenance',
    ]);

    $service = app(TerrainAvailabilityService::class);

    $service->assertSlotCanBeReserved($slot);
})->throws(DomainException::class, 'Selected slot overlaps an inactive terrain period.');

test('service throws when slot start time has already passed', function () {
    CarbonImmutable::setTestNow('2026-03-04 12:00:00');

    try {
        $terrain = Terrain::query()->create([
            'name' => 'Past Slot Court',
            'code' => 'past-slot-court',
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

        $slot = ReservationSlot::query()->create([
            'terrain_id' => $terrain->id,
            'starts_at' => CarbonImmutable::now()->setTime(11, 0),
            'ends_at' => CarbonImmutable::now()->setTime(12, 0),
            'status' => ReservationSlotStatus::Available,
        ]);

        $service = app(TerrainAvailabilityService::class);

        $service->assertSlotCanBeReserved($slot);
    } finally {
        CarbonImmutable::setTestNow();
    }
})->throws(DomainException::class, 'Slot start time has already passed.');
