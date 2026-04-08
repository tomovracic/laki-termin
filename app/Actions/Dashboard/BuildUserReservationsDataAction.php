<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Http\Resources\ReservationResource;
use App\Models\Reservation;

class BuildUserReservationsDataAction
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function execute(int $userId): array
    {
        $reservations = Reservation::query()
            ->ownedBy($userId)
            ->with(['slot.terrain'])
            ->latest()
            ->get();

        return ReservationResource::collection($reservations)->resolve();
    }
}
