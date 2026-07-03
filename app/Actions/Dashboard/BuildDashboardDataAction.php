<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Actions\Reservations\SyncTerrainSlotsForDateAction;
use App\Http\Resources\DashboardTerrainResource;
use App\Models\ReservationSlot;
use App\Models\Terrain;
use App\Models\TerrainInactivePeriod;
use App\Models\TerrainSetting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class BuildDashboardDataAction
{
    public function __construct(
        protected SyncTerrainSlotsForDateAction $syncTerrainSlotsForDateAction,
    ) {}

    /**
     * @return array{selected_date: string, max_advance_days: int, terrains: array<int, array<string, mixed>>}
     */
    public function execute(?string $requestedDate): array
    {
        $selectedDate = $this->resolveDate($requestedDate);
        $setting = TerrainSetting::query()->global()->first();
        $maxAdvanceDays = $setting?->max_advance_days ?? 30;
        $dayStartsAt = $selectedDate->startOfDay()->toDateTimeString();
        $dayEndsAt = $selectedDate->endOfDay()->toDateTimeString();

        $activeTerrains = Terrain::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'description', 'is_active']);

        foreach ($activeTerrains as $terrain) {
            $this->syncTerrainSlotsForDateAction->execute($terrain, $selectedDate);
        }

        $inactivePeriods = TerrainInactivePeriod::query()
            ->overlapping($dayStartsAt, $dayEndsAt)
            ->get(['id', 'terrain_id', 'from_at', 'to_at', 'reason', 'note']);

        $terrains = $activeTerrains->load([
            'reservationSlots' => function ($query) use ($dayEndsAt, $dayStartsAt): void {
                $query
                    ->between($dayStartsAt, $dayEndsAt)
                    ->available()
                    ->orderBy('starts_at');
            },
        ]);

        $terrains->each(function (Terrain $terrain) use ($inactivePeriods): void {
            $terrainInactivePeriods = $this->inactivePeriodsForTerrain($inactivePeriods, $terrain->id);
            $availableSlots = $terrain->reservationSlots->filter(
                fn (ReservationSlot $slot): bool => ! $this->slotOverlapsInactivePeriods($slot, $terrainInactivePeriods),
            );

            $terrain->setRelation('reservationSlots', $availableSlots->values());
            $terrain->setAttribute('reservation_slots_count', $availableSlots->count());

            $blockedPeriod = $terrainInactivePeriods->first();

            if ($blockedPeriod !== null) {
                $terrain->setAttribute('blocked_for_day', [
                    'reason' => $blockedPeriod->reason,
                    'note' => $blockedPeriod->note,
                ]);
            }
        });

        return [
            'selected_date' => $selectedDate->toDateString(),
            'max_advance_days' => $maxAdvanceDays,
            'terrains' => DashboardTerrainResource::collection($terrains)->resolve(),
        ];
    }

    protected function resolveDate(?string $requestedDate): CarbonImmutable
    {
        if ($requestedDate === null || $requestedDate === '') {
            return CarbonImmutable::today();
        }

        return CarbonImmutable::createFromFormat('Y-m-d', $requestedDate)->startOfDay();
    }

    /**
     * @param  Collection<int, TerrainInactivePeriod>  $inactivePeriods
     * @return Collection<int, TerrainInactivePeriod>
     */
    protected function inactivePeriodsForTerrain(Collection $inactivePeriods, int $terrainId): Collection
    {
        return $inactivePeriods->filter(
            fn (TerrainInactivePeriod $period): bool => $period->terrain_id === null
                || $period->terrain_id === $terrainId,
        )->values();
    }

    /**
     * @param  Collection<int, TerrainInactivePeriod>  $inactivePeriods
     */
    protected function slotOverlapsInactivePeriods(
        ReservationSlot $slot,
        Collection $inactivePeriods,
    ): bool {
        if ($inactivePeriods->isEmpty() || $slot->starts_at === null || $slot->ends_at === null) {
            return false;
        }

        $slotStartsAt = $slot->starts_at->toDateTimeString();
        $slotEndsAt = $slot->ends_at->toDateTimeString();

        return $inactivePeriods->contains(
            fn (TerrainInactivePeriod $period): bool => $period->from_at < $slotEndsAt
                && $period->to_at > $slotStartsAt,
        );
    }
}
