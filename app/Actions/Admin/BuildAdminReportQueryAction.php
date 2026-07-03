<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\ReservationStatus;
use App\Http\Requests\Admin\IndexAdminCancelledReservationReportRequest;
use App\Http\Requests\Admin\IndexAdminLoginReportRequest;
use App\Http\Requests\Admin\IndexAdminReservationReportRequest;
use App\Models\Reservation;
use App\Models\UserLoginLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class BuildAdminReportQueryAction
{
    public function loginLogs(IndexAdminLoginReportRequest $request): LengthAwarePaginator
    {
        return UserLoginLog::query()
            ->with('user:id,first_name,last_name,email')
            ->when(
                $request->filled('from_date'),
                fn (Builder $query) => $query->whereDate('logged_in_at', '>=', $request->string('from_date')->toString()),
            )
            ->when(
                $request->filled('to_date'),
                fn (Builder $query) => $query->whereDate('logged_in_at', '<=', $request->string('to_date')->toString()),
            )
            ->when(
                $request->filled('user_id'),
                fn (Builder $query) => $query->where('user_id', $request->integer('user_id')),
            )
            ->when(
                $request->filled('search'),
                function (Builder $query) use ($request): void {
                    $term = '%'.$request->string('search')->trim()->toString().'%';

                    $query->whereHas(
                        'user',
                        fn (Builder $userQuery) => $userQuery
                            ->where('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term)
                            ->orWhere('email', 'like', $term),
                    );
                },
            )
            ->latest('logged_in_at')
            ->paginate(20)
            ->withQueryString();
    }

    public function reservedReservations(IndexAdminReservationReportRequest $request): LengthAwarePaginator
    {
        return $this->baseReservationQuery($request)
            ->active()
            ->when(
                $request->filled('from_date'),
                fn (Builder $query) => $query->whereDate('reserved_for_date', '>=', $request->string('from_date')->toString()),
            )
            ->when(
                $request->filled('to_date'),
                fn (Builder $query) => $query->whereDate('reserved_for_date', '<=', $request->string('to_date')->toString()),
            )
            ->when(
                $request->string('period')->toString() === 'upcoming',
                fn (Builder $query) => $query->whereHas(
                    'slot',
                    fn (Builder $slotQuery) => $slotQuery->where('ends_at', '>=', now()),
                ),
            )
            ->when(
                $request->string('period')->toString() === 'past',
                fn (Builder $query) => $query->whereHas(
                    'slot',
                    fn (Builder $slotQuery) => $slotQuery->where('ends_at', '<', now()),
                ),
            )
            ->latest('reserved_for_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();
    }

    public function cancelledReservations(IndexAdminCancelledReservationReportRequest $request): LengthAwarePaginator
    {
        return $this->baseReservationQuery($request)
            ->where('status', ReservationStatus::Cancelled)
            ->when(
                $request->filled('from_date'),
                fn (Builder $query) => $query->whereDate('cancelled_at', '>=', $request->string('from_date')->toString()),
            )
            ->when(
                $request->filled('to_date'),
                fn (Builder $query) => $query->whereDate('cancelled_at', '<=', $request->string('to_date')->toString()),
            )
            ->latest('cancelled_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();
    }

    private function baseReservationQuery(
        IndexAdminReservationReportRequest|IndexAdminCancelledReservationReportRequest $request,
    ): Builder {
        return Reservation::query()
            ->with(['user:id,first_name,last_name,email', 'slot.terrain'])
            ->when(
                $request->filled('user_id'),
                fn (Builder $query) => $query->where('user_id', $request->integer('user_id')),
            )
            ->when(
                $request->filled('terrain_id'),
                fn (Builder $query) => $query->whereHas(
                    'slot',
                    fn (Builder $slotQuery) => $slotQuery->where('terrain_id', $request->integer('terrain_id')),
                ),
            );
    }
}
