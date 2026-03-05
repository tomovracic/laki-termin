<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Actions\Reservations\SyncTerrainSlotsForDateAction;
use App\Http\Resources\ReservationSlotResource;
use App\Models\ReservationSlot;
use App\Models\Terrain;
use App\Models\TerrainSetting;
use Carbon\CarbonImmutable;

class BuildTerrainSlotsDataAction
{
    public function __construct(
        protected SyncTerrainSlotsForDateAction $syncTerrainSlotsForDateAction,
    ) {}

    /**
     * @return array{selected_date: string, max_advance_days: int, slots: array<int, array<string, mixed>>}
     */
    public function execute(Terrain $terrain, ?string $requestedDate): array
    {
        $maxAdvanceDays = TerrainSetting::query()->global()->value('max_advance_days') ?? 30;
        $selectedDate = $this->resolveDate($requestedDate, $maxAdvanceDays);
        $dayStartsAt = $selectedDate->startOfDay()->toDateTimeString();
        $dayEndsAt = $selectedDate->endOfDay()->toDateTimeString();

        $this->syncTerrainSlotsForDateAction->execute($terrain, $selectedDate);

        $slots = ReservationSlot::query()
            ->with(['reservation.user:id,first_name,last_name'])
            ->forTerrain($terrain->id)
            ->between($dayStartsAt, $dayEndsAt)
            ->orderBy('starts_at')
            ->get();

        return [
            'selected_date' => $selectedDate->toDateString(),
            'max_advance_days' => $maxAdvanceDays,
            'slots' => ReservationSlotResource::collection($slots)->resolve(),
        ];
    }

    protected function resolveDate(?string $requestedDate, int $maxAdvanceDays): CarbonImmutable
    {
        $today = CarbonImmutable::today();
        $maxSelectableDate = $today->addDays($maxAdvanceDays);

        if ($requestedDate === null || $requestedDate === '') {
            return $today;
        }

        $selectedDate = CarbonImmutable::createFromFormat('Y-m-d', $requestedDate)->startOfDay();

        if ($selectedDate->greaterThan($maxSelectableDate)) {
            return $maxSelectableDate;
        }

        return $selectedDate;
    }
}
