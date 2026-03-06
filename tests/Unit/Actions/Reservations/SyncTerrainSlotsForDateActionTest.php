<?php

declare(strict_types=1);

use App\Actions\Reservations\SyncTerrainSlotsForDateAction;
use App\Enums\ReservationSlotStatus;
use App\Models\ReservationSlot;
use App\Models\Terrain;
use App\Models\TerrainSetting;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('sync creates expected hourly slots for configured period', function () {
    $terrain = Terrain::query()->create([
        'name' => 'Court Sync Base',
        'code' => 'court-sync-base',
        'is_active' => true,
    ]);

    TerrainSetting::query()->create([
        'terrain_id' => null,
        'is_global' => true,
        'max_advance_days' => 30,
        'availability_periods' => [
            [
                'from' => '12:00',
                'to' => '16:00',
                'slot_duration_minutes' => 60,
            ],
        ],
    ]);

    $selectedDate = CarbonImmutable::now()->addDay()->startOfDay();

    app(SyncTerrainSlotsForDateAction::class)->execute($terrain, $selectedDate);

    $slots = ReservationSlot::query()
        ->forTerrain($terrain->id)
        ->between(
            $selectedDate->startOfDay()->toDateTimeString(),
            $selectedDate->endOfDay()->toDateTimeString(),
        )
        ->orderBy('starts_at')
        ->get();

    expect($slots)->toHaveCount(4);
    expect($slots->map(fn (ReservationSlot $slot): string => $slot->starts_at->format('H:i'))->all())
        ->toBe(['12:00', '13:00', '14:00', '15:00']);
    expect($slots->map(fn (ReservationSlot $slot): string => $slot->ends_at->format('H:i'))->all())
        ->toBe(['13:00', '14:00', '15:00', '16:00']);
});

test('sync does not create overlapping generated slot over protected legacy slot', function () {
    $terrain = Terrain::query()->create([
        'name' => 'Court Sync Protected',
        'code' => 'court-sync-protected',
        'is_active' => true,
    ]);

    TerrainSetting::query()->create([
        'terrain_id' => null,
        'is_global' => true,
        'max_advance_days' => 30,
        'availability_periods' => [
            [
                'from' => '12:00',
                'to' => '16:00',
                'slot_duration_minutes' => 60,
            ],
        ],
    ]);

    $selectedDate = CarbonImmutable::now()->addDay()->startOfDay();

    ReservationSlot::query()->create([
        'terrain_id' => $terrain->id,
        'starts_at' => $selectedDate->setTime(12, 0),
        'ends_at' => $selectedDate->setTime(12, 45),
        'status' => ReservationSlotStatus::Reserved,
    ]);

    app(SyncTerrainSlotsForDateAction::class)->execute($terrain, $selectedDate);

    $slots = ReservationSlot::query()
        ->forTerrain($terrain->id)
        ->between(
            $selectedDate->startOfDay()->toDateTimeString(),
            $selectedDate->endOfDay()->toDateTimeString(),
        )
        ->orderBy('starts_at')
        ->get();

    expect($slots)->toHaveCount(4);
    expect($slots->contains(
        fn (ReservationSlot $slot): bool => $slot->starts_at->format('H:i') === '12:00'
            && $slot->ends_at->format('H:i') === '13:00'
    ))->toBeFalse();
    expect($slots->contains(
        fn (ReservationSlot $slot): bool => $slot->starts_at->format('H:i') === '13:00'
            && $slot->ends_at->format('H:i') === '14:00'
    ))->toBeTrue();
});
