<?php

declare(strict_types=1);

namespace App\DTO\Reservations;

readonly class CreateReservationData
{
    public function __construct(
        public int $userId,
        public int $reservationSlotId,
    ) {}
}
