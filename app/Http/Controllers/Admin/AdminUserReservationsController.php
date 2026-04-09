<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReservationResource;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AdminUserReservationsController extends Controller
{
    public function __invoke(Request $request, User $user): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        $reservations = Reservation::query()
            ->ownedBy($user->id)
            ->with(['slot.terrain'])
            ->latest()
            ->paginate(8)
            ->withQueryString();

        return ReservationResource::collection($reservations);
    }
}
