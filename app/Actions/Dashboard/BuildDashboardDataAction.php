<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Actions\Reservations\SyncTerrainSlotsForDateAction;
use App\Http\Resources\DashboardTerrainResource;
use App\Models\Terrain;
use App\Models\TerrainSetting;
use Carbon\CarbonImmutable;

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

        $terrains = $activeTerrains->load([
            'reservationSlots' => function ($query) use ($dayEndsAt, $dayStartsAt): void {
                $query
                    ->between($dayStartsAt, $dayEndsAt)
                    ->available()
                    ->orderBy('starts_at');
            },
        ]);

        $terrains->each(
            fn (Terrain $terrain): Terrain => $terrain->setAttribute(
                'reservation_slots_count',
                $terrain->reservationSlots->count(),
            )
        );

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
}
