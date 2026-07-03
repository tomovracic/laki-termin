<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\BuildAdminReportFilterOptionsAction;
use App\Actions\Admin\BuildAdminReportQueryAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexAdminLoginReportRequest;
use App\Http\Resources\UserLoginLogResource;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AdminLoginReportController extends Controller
{
    public function __invoke(
        IndexAdminLoginReportRequest $request,
        BuildAdminReportQueryAction $buildAdminReportQueryAction,
        BuildAdminReportFilterOptionsAction $buildAdminReportFilterOptionsAction,
    ): Response {
        Gate::authorize('viewAny', User::class);

        $logs = $buildAdminReportQueryAction->loginLogs($request);
        $filterOptions = $buildAdminReportFilterOptionsAction->execute();

        return Inertia::render('admin/reports/logins', [
            'logs' => UserLoginLogResource::collection($logs),
            'users' => $filterOptions['users'],
            'filters' => [
                'from_date' => $request->string('from_date')->toString() ?: null,
                'to_date' => $request->string('to_date')->toString() ?: null,
                'user_id' => $request->filled('user_id') ? $request->integer('user_id') : null,
                'search' => $request->string('search')->toString() ?: null,
            ],
        ]);
    }
}
