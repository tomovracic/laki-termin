<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Actions\Reservations\SyncTerrainSlotsForDateAction;
use App\Http\Resources\ReservationSlotResource;
use App\Models\ReservationSlot;
use App\Models\Terrain;
use Carbon\CarbonImmutable;

class BuildTerrainSlotsDataAction
{
    public function __construct(
        protected SyncTerrainSlotsForDateAction $syncTerrainSlotsForDateAction,
    ) {}

    /**
     * @return array{selected_date: string, slots: array<int, array<string, mixed>>}
     */
    public function execute(Terrain $terrain, ?string $requestedDate): array
    {
        $selectedDate = $this->resolveDate($requestedDate);
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
            'slots' => ReservationSlotResource::collection($slots)->resolve(),
        ];
    }

    protected function resolveDate(?string $requestedDate): CarbonImmutable
    {
        if ($requestedDate === null || $requestedDate === '') {
            return CarbonImmutable::today();
        }

        return CarbonImmutable::createFromFormat('Y-m-d', $requestedDate)->startOfDay();
    }
}
