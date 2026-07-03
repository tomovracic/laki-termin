<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\BuildAdminReportFilterOptionsAction;
use App\Actions\Admin\BuildAdminReportQueryAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexAdminReservationReportRequest;
use App\Http\Resources\ReservationResource;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AdminReservedReservationsReportController extends Controller
{
    public function __invoke(
        IndexAdminReservationReportRequest $request,
        BuildAdminReportQueryAction $buildAdminReportQueryAction,
        BuildAdminReportFilterOptionsAction $buildAdminReportFilterOptionsAction,
    ): Response {
        Gate::authorize('viewAny', User::class);

        $reservations = $buildAdminReportQueryAction->reservedReservations($request);
        $filterOptions = $buildAdminReportFilterOptionsAction->execute();

        return Inertia::render('admin/reports/reserved', [
            'reservations' => ReservationResource::collection($reservations),
            'users' => $filterOptions['users'],
            'terrains' => $filterOptions['terrains'],
            'filters' => [
                'from_date' => $request->string('from_date')->toString() ?: null,
                'to_date' => $request->string('to_date')->toString() ?: null,
                'user_id' => $request->filled('user_id') ? $request->integer('user_id') : null,
                'terrain_id' => $request->filled('terrain_id') ? $request->integer('terrain_id') : null,
                'period' => $request->string('period')->toString() ?: 'all',
            ],
        ]);
    }
}
