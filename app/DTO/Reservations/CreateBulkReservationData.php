<?php

declare(strict_types=1);

namespace App\DTO\Reservations;

class CreateBulkReservationData
{
    /**
     * @param list<int> $reservationSlotIds
     */
    public function __construct(
        public readonly int $userId,
        public readonly array $reservationSlotIds,
    ) {}
}
