<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\Dashboard\BuildUserReservationsDataAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserReservationsController extends Controller
{
    public function __invoke(
        Request $request,
        BuildUserReservationsDataAction $userReservationsDataAction,
    ): Response {
        return Inertia::render('dashboard/reservations', [
            'reservations' => $userReservationsDataAction->execute((int) $request->user()?->id),
        ]);
    }
}
