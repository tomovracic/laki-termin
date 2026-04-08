<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reservations;

use App\Actions\Reservations\CancelReservationAction;
use App\Actions\Reservations\CreateBulkReservationAction;
use App\Actions\Reservations\CreateReservationAction;
use App\DTO\Reservations\CreateBulkReservationData;
use App\DTO\Reservations\CreateReservationData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reservations\CancelReservationRequest;
use App\Http\Requests\Reservations\StoreBulkReservationRequest;
use App\Http\Requests\Reservations\StoreReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Models\Reservation;
use App\Models\User;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReservationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Reservation::class);

        $reservations = Reservation::query()
            ->with(['slot.terrain'])
            ->ownedBy($request->user()->id)
            ->latest()
            ->paginate(20);

        return ReservationResource::collection($reservations);
    }

    public function store(StoreReservationRequest $request, CreateReservationAction $action): ReservationResource|JsonResponse
    {
        try {
            $reservation = $action->execute(new CreateReservationData(
                userId: $request->user()->id,
                reservationSlotId: (int) $request->validated('reservation_slot_id'),
            ));
        } catch (DomainException $exception) {
            return response()->json([
                'errors' => [
                    'reservation' => [$exception->getMessage()],
                ],
            ], 422);
        }

        return ReservationResource::make($reservation);
    }

    public function storeBulk(
        StoreBulkReservationRequest $request,
        CreateBulkReservationAction $action,
    ): JsonResponse {
        try {
            $reservations = $action->execute(new CreateBulkReservationData(
                userId: $request->user()->id,
                reservationSlotIds: array_map(
                    static fn (mixed $slotId): int => (int) $slotId,
                    $request->validated('reservation_slot_ids'),
                ),
            ));
        } catch (DomainException $exception) {
            return response()->json([
                'errors' => [
                    'reservation' => [$exception->getMessage()],
                ],
            ], 422);
        }

        return response()->json([
            'data' => ReservationResource::collection($reservations)->resolve(),
            'meta' => [
                'tokens_remaining' => $request->user()->fresh()?->token_count ?? 0,
            ],
        ], 201);
    }

    public function show(Reservation $reservation): ReservationResource
    {
        $this->authorize('view', $reservation);

        return ReservationResource::make($reservation->load(['slot.terrain', 'tokens']));
    }

    public function cancel(
        CancelReservationRequest $request,
        Reservation $reservation,
        CancelReservationAction $action,
    ): ReservationResource|JsonResponse {
        /** @var User $user */
        $user = $request->user();

        try {
            $reservation = $action->execute(
                $reservation,
                $user,
                $request->validated('cancel_reason'),
            );
        } catch (DomainException $exception) {
            return response()->json([
                'errors' => [
                    'reservation' => [$exception->getMessage()],
                ],
            ], 422);
        }

        return ReservationResource::make($reservation);
    }
}
