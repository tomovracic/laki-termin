<?php

declare(strict_types=1);

namespace App\Actions\Reservations;

use App\Enums\ReservationSlotStatus;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\TerrainSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\DatabaseManager;

class CancelReservationAction
{
    private const BUSINESS_TIMEZONE = 'Europe/Zagreb';

    public function __construct(
        protected DatabaseManager $database,
    ) {}

    public function execute(
        Reservation $reservation,
        User $actor,
        ?string $reason = null,
    ): Reservation {
        return $this->database->transaction(function () use ($reservation, $actor, $reason): Reservation {
            $reservation->loadMissing('slot', 'user');
            $isAdmin = $actor->hasRole('admin');

            if (! in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Confirmed], true)) {
                throw new DomainException('Only active reservations can be cancelled.');
            }

            if ($reservation->slot->starts_at === null) {
                throw new DomainException('Cannot cancel a reservation for a slot that has already started.');
            }

            if (! $isAdmin) {
                $now = CarbonImmutable::now(self::BUSINESS_TIMEZONE);
                $slotStart = CarbonImmutable::parse(
                    $reservation->slot->starts_at->toDateTimeString(),
                    self::BUSINESS_TIMEZONE,
                );

                if (! $slotStart->isFuture()) {
                    throw new DomainException('Cannot cancel a reservation for a slot that has already started.');
                }

                $settings = TerrainSetting::query()
                    ->global()
                    ->first(['cancellation_cutoff_hours']);
                $cutoffHours = $settings?->cancellation_cutoff_hours ?? 0;

                if ($cutoffHours > 0 && $now->addHours($cutoffHours)->greaterThanOrEqualTo($slotStart)) {
                    throw new DomainException('Cannot cancel reservation inside cancellation cutoff window.');
                }
            }

            $reservation->update([
                'status' => ReservationStatus::Cancelled,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            $reservation->slot->update([
                'status' => ReservationSlotStatus::Available,
            ]);

            /** @var User $user */
            $user = User::query()
                ->lockForUpdate()
                ->findOrFail($reservation->user_id);

            $user->update([
                'token_count' => ($user->token_count ?? 0) + 1,
            ]);

            return $reservation->fresh(['slot', 'tokens']);
        });
    }
}
