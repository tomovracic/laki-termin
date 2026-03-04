<?php

declare(strict_types=1);

namespace App\Actions\Reservations;

use App\Enums\ReservationSlotStatus;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use DomainException;
use Illuminate\Database\DatabaseManager;

class CancelReservationAction
{
    public function __construct(
        protected DatabaseManager $database,
    ) {}

    public function execute(Reservation $reservation, ?string $reason = null): Reservation
    {
        return $this->database->transaction(function () use ($reservation, $reason): Reservation {
            $reservation->loadMissing('slot');

            if (! in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Confirmed], true)) {
                throw new DomainException('Only active reservations can be cancelled.');
            }

            if ($reservation->slot->starts_at === null || ! $reservation->slot->starts_at->isFuture()) {
                throw new DomainException('Cannot cancel a reservation for a slot that has already started.');
            }

            $reservation->update([
                'status' => ReservationStatus::Cancelled,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            $reservation->slot->update([
                'status' => ReservationSlotStatus::Available,
            ]);

            return $reservation->fresh(['slot', 'tokens']);
        });
    }
}
